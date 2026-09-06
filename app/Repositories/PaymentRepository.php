<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use App\Security\Security;
use App\Services\Payments\GatewayVerification;
use App\Services\Payments\PaymentRepositoryInterface;
use App\Services\Notifications\NotificationOutbox;
use DateTimeImmutable;
use DomainException;
use PDO;
use PDOException;

final class PaymentRepository extends Repository implements PaymentRepositoryInterface
{
    public function __construct(Connection $database) { parent::__construct($database); }

    public function invoicesForUser(int $userId): array
    {
        $statement = $this->connection()->prepare("SELECT public_id,invoice_number,currency,total_minor,amount_paid_minor,status,due_at,paid_at,created_at FROM invoices WHERE user_id=:user ORDER BY created_at DESC,id DESC");
        $statement->execute(['user' => $userId]);
        return $statement->fetchAll();
    }

    public function initiate(int $userId, string $invoicePublicId, string $gateway, string $reference, string $keyHash, string $requestHash, DateTimeImmutable $now): array
    {
        try {
            return $this->database->transaction(function (PDO $db) use ($userId, $invoicePublicId, $gateway, $reference, $keyHash, $requestHash, $now): array {
                $scope = 'payment.initialize.user.' . $userId;
                $claim = $db->prepare('SELECT request_hash,resource_public_id FROM idempotency_keys WHERE scope=:scope AND key_hash=:key LIMIT 1 FOR UPDATE');
                $claim->execute(['scope' => $scope, 'key' => $keyHash]);
                $existingClaim = $claim->fetch();
                if (is_array($existingClaim)) {
                    if (!hash_equals((string) $existingClaim['request_hash'], $requestHash)) throw new DomainException('The idempotency key was already used for another request.');
                    $existing = $this->paymentInitialization($db, (string) $existingClaim['resource_public_id'], $userId);
                    if ($existing === null) throw new DomainException('The earlier payment request cannot be resumed.');
                    $existing['idempotent'] = true;
                    return $existing;
                }

                $stamp = $this->date($now);
                $db->prepare("INSERT INTO idempotency_keys (scope,key_hash,request_hash,status,expires_at,created_at,updated_at) VALUES (:scope,:key,:request,'processing',:expires,:now,:now)")
                    ->execute(['scope' => $scope, 'key' => $keyHash, 'request' => $requestHash, 'expires' => $now->modify('+24 hours')->format('Y-m-d H:i:s.u'), 'now' => $stamp]);

                $invoiceStatement = $db->prepare("SELECT id,public_id,invoice_number,bill_to_email,currency,total_minor,amount_paid_minor,status,due_at FROM invoices WHERE public_id=:invoice AND user_id=:user LIMIT 1 FOR UPDATE");
                $invoiceStatement->execute(['invoice' => $invoicePublicId, 'user' => $userId]);
                $invoice = $invoiceStatement->fetch();
                if (!is_array($invoice)) throw new DomainException('Invoice not found.');
                if ($invoice['status'] !== 'pending' || (int) $invoice['amount_paid_minor'] !== 0) throw new DomainException('This invoice is not payable.');
                if ($invoice['due_at'] !== null && (string) $invoice['due_at'] < $stamp) throw new DomainException('This invoice has expired.');

                $pending = $db->prepare("SELECT public_id FROM payments WHERE invoice_id=:invoice AND gateway=:gateway AND status IN ('initiated','pending') ORDER BY id DESC LIMIT 1 FOR UPDATE");
                $pending->execute(['invoice' => $invoice['id'], 'gateway' => $gateway]);
                $paymentPublicId = $pending->fetchColumn();
                if ($paymentPublicId === false) {
                    $paymentPublicId = Security::uuidV4();
                    $db->prepare("INSERT INTO payments (public_id,invoice_id,user_id,gateway,gateway_reference,amount_minor,currency,status,created_at,updated_at) VALUES (:public_id,:invoice,:user,:gateway,:reference,:amount,:currency,'initiated',:now,:now)")
                        ->execute(['public_id' => $paymentPublicId, 'invoice' => $invoice['id'], 'user' => $userId, 'gateway' => $gateway, 'reference' => $reference, 'amount' => (int) $invoice['total_minor'], 'currency' => $invoice['currency'], 'now' => $stamp]);
                }
                $db->prepare("UPDATE idempotency_keys SET status='completed',resource_type='payment',resource_public_id=:resource,response_status=200,updated_at=:now WHERE scope=:scope AND key_hash=:key")
                    ->execute(['resource' => $paymentPublicId, 'now' => $stamp, 'scope' => $scope, 'key' => $keyHash]);
                $this->audit($db, $userId, 'payment.initialization_requested', 'payment', (string) $paymentPublicId, ['invoice_number' => $invoice['invoice_number'], 'gateway' => $gateway], $now);
                $result = $this->paymentInitialization($db, (string) $paymentPublicId, $userId);
                if ($result === null) throw new DomainException('Payment could not be initialized.');
                $result['idempotent'] = false;
                return $result;
            });
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') throw new DomainException('This payment request was already received.');
            throw $e;
        }
    }

    public function recordInitialization(string $paymentPublicId, bool $accepted, array $payload, DateTimeImmutable $now): void
    {
        $this->database->transaction(function (PDO $db) use ($paymentPublicId, $accepted, $payload, $now): void {
            $payment = $db->prepare('SELECT id,gateway,gateway_reference FROM payments WHERE public_id=:id LIMIT 1 FOR UPDATE');
            $payment->execute(['id' => $paymentPublicId]);
            $row = $payment->fetch();
            if (!is_array($row)) throw new DomainException('Payment not found.');
            $clean = $this->sanitizePayload($payload);
            $encoded = $this->encode($clean);
            $db->prepare("INSERT INTO payment_transactions (public_id,payment_id,gateway,gateway_reference,transaction_type,provider_status,response_payload,response_hash,processed,processed_at,created_at) VALUES (:public_id,:payment,:gateway,:reference,'initialize',:status,:payload,:hash,1,:now,:now)")
                ->execute(['public_id' => Security::uuidV4(), 'payment' => $row['id'], 'gateway' => $row['gateway'], 'reference' => $row['gateway_reference'], 'status' => $accepted ? 'accepted' : 'rejected', 'payload' => $encoded, 'hash' => hash('sha256', $encoded), 'now' => $this->date($now)]);
            $db->prepare("UPDATE payments SET status=:status,failure_reason=:reason,updated_at=:now WHERE id=:id AND status='initiated'")
                ->execute(['status' => $accepted ? 'pending' : 'failed', 'reason' => $accepted ? null : 'Provider rejected initialization.', 'now' => $this->date($now), 'id' => $row['id']]);
        });
    }

    public function settle(string $gateway, string $reference, GatewayVerification $verification, string $transactionType, ?string $providerEventId, DateTimeImmutable $now): array
    {
        try {
            return $this->database->transaction(function (PDO $db) use ($gateway, $reference, $verification, $transactionType, $providerEventId, $now): array {
            if ($providerEventId !== null) {
                $replay = $db->prepare('SELECT transactions.processed,invoices.public_id AS invoice_public_id FROM payment_transactions transactions LEFT JOIN payments ON payments.id=transactions.payment_id LEFT JOIN invoices ON invoices.id=payments.invoice_id WHERE transactions.gateway=:gateway AND transactions.provider_event_id=:event LIMIT 1 FOR UPDATE');
                $replay->execute(['gateway' => $gateway, 'event' => $providerEventId]);
                $replayed = $replay->fetch();
                if (is_array($replayed)) return ['accepted' => true, 'idempotent' => true, 'status' => 'already_processed', 'invoice_public_id' => $replayed['invoice_public_id']];
            }
            $paymentStatement = $db->prepare('SELECT payments.*,invoices.public_id AS invoice_public_id,invoices.invoice_number,invoices.total_minor,invoices.amount_paid_minor,invoices.currency AS invoice_currency,invoices.status AS invoice_status,invoices.due_at AS invoice_due_at FROM payments INNER JOIN invoices ON invoices.id=payments.invoice_id WHERE payments.gateway=:gateway AND payments.gateway_reference=:reference LIMIT 1 FOR UPDATE');
            $paymentStatement->execute(['gateway' => $gateway, 'reference' => $reference]);
            $payment = $paymentStatement->fetch();
            $clean = $this->sanitizePayload($verification->rawResponse);
            $encoded = $this->encode($clean);
            $transactionId = Security::uuidV4();
            $db->prepare('INSERT INTO payment_transactions (public_id,payment_id,gateway,gateway_reference,provider_event_id,transaction_type,provider_status,amount_minor,currency,response_payload,response_hash,processed,created_at) VALUES (:public_id,:payment,:gateway,:reference,:event,:type,:status,:amount,:currency,:payload,:hash,0,:now)')
                ->execute(['public_id' => $transactionId, 'payment' => is_array($payment) ? $payment['id'] : null, 'gateway' => $gateway, 'reference' => $reference, 'event' => $providerEventId, 'type' => $transactionType, 'status' => $verification->status, 'amount' => $verification->amountMinor, 'currency' => strtoupper($verification->currency), 'payload' => $encoded, 'hash' => hash('sha256', $encoded), 'now' => $this->date($now)]);
            if (!is_array($payment)) {
                $this->securityEvent($db, null, 'payment.invalid_reference', 'high', 'Provider verification referenced an unknown payment.', ['gateway' => $gateway, 'reference_hash' => hash('sha256', $reference)], $now);
                return ['accepted' => false, 'idempotent' => false, 'reason' => 'Payment reference does not match an invoice.'];
            }
            $mismatch = null;
            if (!hash_equals($reference, $verification->reference)) $mismatch = 'Provider reference mismatch.';
            elseif ($verification->status !== 'successful') $mismatch = 'Provider has not confirmed a successful payment.';
            elseif ($verification->amountMinor !== (int) $payment['amount_minor'] || $verification->amountMinor !== (int) $payment['total_minor']) $mismatch = 'Provider amount does not match the invoice.';
            elseif (!hash_equals(strtoupper((string) $payment['currency']), strtoupper($verification->currency)) || !hash_equals(strtoupper((string) $payment['invoice_currency']), strtoupper($verification->currency))) $mismatch = 'Provider currency does not match the invoice.';
            elseif ($verification->invoiceNumber === null || !hash_equals((string) $payment['invoice_number'], $verification->invoiceNumber)) $mismatch = 'Provider metadata does not match the invoice.';
            elseif ($payment['invoice_due_at'] !== null && (string) $payment['invoice_due_at'] < $this->date($now)) $mismatch = 'Invoice expired before payment verification.';
            if ($mismatch !== null) {
                $db->prepare("UPDATE payments SET status='failed',failure_reason=:reason,verified_at=:now,updated_at=:now WHERE id=:id AND status<>'successful'")->execute(['reason' => $mismatch, 'now' => $this->date($now), 'id' => $payment['id']]);
                $this->finishTransaction($db, $transactionId, false, $now);
                $this->audit($db, $payment['user_id'] !== null ? (int) $payment['user_id'] : null, 'payment.verification_failed', 'payment', (string) $payment['public_id'], ['reason' => $mismatch], $now);
                $this->securityEvent($db, $payment['user_id'] !== null ? (int) $payment['user_id'] : null, 'payment.verification_mismatch', 'high', $mismatch, ['payment_public_id' => $payment['public_id']], $now);
                return ['accepted' => false, 'idempotent' => false, 'reason' => $mismatch];
            }
            if ($payment['status'] === 'successful' || $payment['invoice_status'] === 'paid') {
                $this->finishTransaction($db, $transactionId, true, $now);
                return ['accepted' => true, 'idempotent' => true, 'status' => 'paid', 'invoice_public_id' => $payment['invoice_public_id']];
            }
            if ($payment['invoice_status'] !== 'pending' || (int) $payment['amount_paid_minor'] !== 0) return ['accepted' => false, 'idempotent' => false, 'reason' => 'Invoice is no longer payable.'];
            $stamp = $this->date($now);
            $updatedInvoice = $db->prepare("UPDATE invoices SET amount_paid_minor=total_minor,status='paid',paid_at=:now,updated_at=:now WHERE id=:id AND status='pending' AND amount_paid_minor=0");
            $updatedInvoice->execute(['now' => $stamp, 'id' => $payment['invoice_id']]);
            if ($updatedInvoice->rowCount() !== 1) throw new DomainException('Invoice was concurrently processed.');
            $db->prepare("UPDATE payments SET status='successful',verified_at=:now,paid_at=:now,failure_reason=NULL,updated_at=:now WHERE id=:id AND status<>'successful'")->execute(['now' => $stamp, 'id' => $payment['id']]);
            $this->finishTransaction($db, $transactionId, true, $now);
            $this->audit($db, $payment['user_id'] !== null ? (int) $payment['user_id'] : null, 'payment.verified', 'payment', (string) $payment['public_id'], ['invoice_number' => $payment['invoice_number'], 'gateway' => $gateway, 'amount_minor' => $verification->amountMinor, 'currency' => strtoupper($verification->currency)], $now);
            NotificationOutbox::enqueue($db, 'payment.confirmed', (string) $payment['public_id'], $payment['user_id'] !== null ? (int) $payment['user_id'] : null, null, 'payments', 'Payment confirmed', 'Your payment has been verified and the related invoice is paid.', '/portal/payments', ['in_app', 'email'], $now);
            return ['accepted' => true, 'idempotent' => false, 'status' => 'paid', 'invoice_public_id' => $payment['invoice_public_id']];
            });
        } catch (PDOException $e) {
            // A concurrent delivery can pass the initial lookup before its twin commits.
            if ($providerEventId !== null && $e->getCode() === '23000') {
                $replay = $this->connection()->prepare('SELECT transactions.processed,invoices.public_id AS invoice_public_id FROM payment_transactions transactions LEFT JOIN payments ON payments.id=transactions.payment_id LEFT JOIN invoices ON invoices.id=payments.invoice_id WHERE transactions.gateway=:gateway AND transactions.provider_event_id=:event LIMIT 1');
                $replay->execute(['gateway' => $gateway, 'event' => $providerEventId]);
                $replayed = $replay->fetch();
                if (is_array($replayed)) return ['accepted' => true, 'idempotent' => true, 'status' => 'already_processed', 'invoice_public_id' => $replayed['invoice_public_id']];
            }
            throw $e;
        }
    }

    private function paymentInitialization(PDO $db, string $paymentPublicId, int $userId): ?array
    {
        $s = $db->prepare('SELECT payments.public_id AS payment_public_id,payments.gateway_reference,payments.amount_minor,payments.currency,invoices.invoice_number,invoices.bill_to_email AS email FROM payments INNER JOIN invoices ON invoices.id=payments.invoice_id WHERE payments.public_id=:id AND payments.user_id=:user LIMIT 1');
        $s->execute(['id' => $paymentPublicId, 'user' => $userId]);
        $row = $s->fetch();
        return is_array($row) ? $row : null;
    }

    private function finishTransaction(PDO $db, string $publicId, bool $processed, DateTimeImmutable $now): void
    {
        $db->prepare('UPDATE payment_transactions SET processed=:processed,processed_at=:now WHERE public_id=:id')->execute(['processed' => $processed ? 1 : 0, 'now' => $this->date($now), 'id' => $publicId]);
    }

    /** @param array<string, mixed> $values */
    private function audit(PDO $db, ?int $actor, string $action, string $type, string $id, array $values, DateTimeImmutable $now): void
    {
        $db->prepare("INSERT INTO audit_logs (actor_user_id,action,auditable_type,auditable_id,description,new_values,request_id,created_at) VALUES (:actor,:action,:type,:id,'A finance workflow action was completed.',:values,:request,:now)")
            ->execute(['actor' => $actor, 'action' => $action, 'type' => $type, 'id' => $id, 'values' => $this->encode($values), 'request' => bin2hex(random_bytes(16)), 'now' => $this->date($now)]);
    }

    /** @param array<string, mixed> $context */
    private function securityEvent(PDO $db, ?int $user, string $type, string $severity, string $description, array $context, DateTimeImmutable $now): void
    {
        $db->prepare('INSERT INTO security_events (user_id,event_type,severity,description,context,fingerprint,occurred_at) VALUES (:user,:type,:severity,:description,:context,:fingerprint,:now)')
            ->execute(['user' => $user, 'type' => $type, 'severity' => $severity, 'description' => $description, 'context' => $this->encode($context), 'fingerprint' => hash('sha256', $type . '|' . ($context['payment_public_id'] ?? $context['reference_hash'] ?? 'unknown')), 'now' => $this->date($now)]);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function sanitizePayload(array $payload): array
    {
        $blocked = ['authorization', 'authorization_code', 'card', 'card_number', 'pan', 'cvv', 'cvc', 'expiry', 'exp_month', 'exp_year'];
        $clean = [];
        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), $blocked, true)) continue;
            $clean[(string) $key] = is_array($value) ? $this->sanitizePayload($value) : $value;
        }
        return $clean;
    }

    /** @param array<string, mixed> $value */ private function encode(array $value): string { return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES); }
    private function date(DateTimeImmutable $date): string { return $date->format('Y-m-d H:i:s.u'); }
}
