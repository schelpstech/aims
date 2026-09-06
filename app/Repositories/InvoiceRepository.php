<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use App\Security\Security;
use App\Services\Invoices\InvoiceRepositoryInterface;
use DateTimeImmutable;
use DomainException;
use PDO;
use PDOException;

final class InvoiceRepository extends Repository implements InvoiceRepositoryInterface
{
    public function __construct(Connection $database) { parent::__construct($database); }

    public function issue(array $invoice, array $items, string $keyHash, string $requestHash, DateTimeImmutable $now): array
    {
        try { return $this->database->transaction(function (PDO $db) use ($invoice, $items, $keyHash, $requestHash, $now): array {
            $scope = 'invoice.issue.' . $invoice['source_type'];
            $claim = $db->prepare('SELECT request_hash,resource_public_id FROM idempotency_keys WHERE scope=:scope AND key_hash=:key LIMIT 1 FOR UPDATE');
            $claim->execute(['scope' => $scope, 'key' => $keyHash]); $existing = $claim->fetch();
            if (is_array($existing)) {
                if (!hash_equals((string) $existing['request_hash'], $requestHash)) throw new DomainException('The idempotency key was already used for another request.');
                return ['public_id' => $existing['resource_public_id'], 'idempotent' => true];
            }
            $stamp = $now->format('Y-m-d H:i:s.u');
            $db->prepare("INSERT INTO idempotency_keys (scope,key_hash,request_hash,status,expires_at,created_at,updated_at) VALUES (:scope,:key,:request,'processing',:expires,:now,:now)")->execute(['scope' => $scope, 'key' => $keyHash, 'request' => $requestHash, 'expires' => $now->modify('+24 hours')->format('Y-m-d H:i:s.u'), 'now' => $stamp]);
            $publicId = Security::uuidV4(); $number = 'AIMS-INV-' . $now->format('Y') . '-' . strtoupper(bin2hex(random_bytes(6)));
            $db->prepare("INSERT INTO invoices (public_id,invoice_number,user_id,bill_to_name,bill_to_email,source_type,source_public_id,currency,subtotal_minor,total_minor,status,due_at,created_at,updated_at) VALUES (:public_id,:number,:user,:name,:email,:source_type,:source_id,:currency,:total,:total,'pending',:due,:now,:now)")->execute(['public_id' => $publicId, 'number' => $number, 'user' => $invoice['user_id'], 'name' => $invoice['bill_to_name'], 'email' => $invoice['bill_to_email'], 'source_type' => $invoice['source_type'], 'source_id' => $invoice['source_public_id'], 'currency' => $invoice['currency'], 'total' => $invoice['total_minor'], 'due' => $invoice['due_at'], 'now' => $stamp]);
            $invoiceId = (int) $db->lastInsertId();
            $insert = $db->prepare('INSERT INTO invoice_items (invoice_id,fee_setting_id,fee_type,description,quantity,unit_amount_minor,line_total_minor,created_at) VALUES (:invoice,:fee_setting,:type,:description,:quantity,:unit,:total,:now)');
            $feeLookup = $db->prepare('SELECT id,fee_type,amount_minor,currency FROM fee_settings WHERE public_id=:id AND active=1 AND deleted_at IS NULL LIMIT 1');
            foreach ($items as $item) {
                $feeId = null;
                if ($item['fee_setting_public_id'] !== null) {
                    $feeLookup->execute(['id' => $item['fee_setting_public_id']]); $fee = $feeLookup->fetch();
                    if (!is_array($fee) || $fee['fee_type'] !== $item['fee_type'] || (int) $fee['amount_minor'] !== (int) $item['unit_amount_minor'] || !hash_equals((string) $fee['currency'], (string) $invoice['currency'])) throw new DomainException('Fee setting does not match the invoice item.');
                    $feeId = $fee['id'];
                }
                $insert->execute(['invoice' => $invoiceId, 'fee_setting' => $feeId, 'type' => $item['fee_type'], 'description' => $item['description'], 'quantity' => $item['quantity'], 'unit' => $item['unit_amount_minor'], 'total' => $item['line_total_minor'], 'now' => $stamp]);
            }
            $db->prepare("UPDATE idempotency_keys SET status='completed',resource_type='invoice',resource_public_id=:resource,response_status=201,updated_at=:now WHERE scope=:scope AND key_hash=:key")->execute(['resource' => $publicId, 'now' => $stamp, 'scope' => $scope, 'key' => $keyHash]);
            $db->prepare("INSERT INTO audit_logs (actor_user_id,action,auditable_type,auditable_id,description,new_values,request_id,created_at) VALUES (:user,'invoice.issued','invoice',:id,'An invoice was issued.',:values,:request,:now)")->execute(['user' => $invoice['user_id'], 'id' => $publicId, 'values' => json_encode(['invoice_number' => $number, 'source_type' => $invoice['source_type'], 'total_minor' => $invoice['total_minor'], 'currency' => $invoice['currency']], JSON_THROW_ON_ERROR), 'request' => bin2hex(random_bytes(16)), 'now' => $stamp]);
            return ['public_id' => $publicId, 'invoice_number' => $number, 'idempotent' => false];
        }); } catch (PDOException $e) { if ($e->getCode() === '23000') throw new DomainException('An invoice already exists for this source.'); throw $e; }
    }
}
