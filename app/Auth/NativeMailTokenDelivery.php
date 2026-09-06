<?php

declare(strict_types=1);

namespace App\Auth;

use App\Database\Connection;
use App\Logging\Logger;
use App\Security\Security;
use DateTimeImmutable;

final class NativeMailTokenDelivery implements TokenDeliveryInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly string $applicationUrl,
        private readonly Logger $logger,
        private readonly ?Connection $database = null,
    ) {
    }

    public function sendEmailVerification(string $email, string $token, DateTimeImmutable $expiresAt): bool
    {
        return $this->send(
            $email,
            'Verify your AIMS Nigeria email address',
            '/verify-email?token=' . rawurlencode($token),
            'Verify your email address',
            $expiresAt,
        );
    }

    public function sendPasswordReset(string $email, string $token, DateTimeImmutable $expiresAt): bool
    {
        return $this->send(
            $email,
            'Reset your AIMS Nigeria password',
            '/reset-password?token=' . rawurlencode($token),
            'Reset your password',
            $expiresAt,
        );
    }

    private function send(string $email, string $subject, string $path, string $action, DateTimeImmutable $expiresAt): bool
    {
        $enabled = (bool) ($this->config['enabled'] ?? false);
        $fromAddress = trim((string) ($this->config['from_address'] ?? ''));
        $baseUrl = rtrim($this->applicationUrl, '/');

        if (!$enabled || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL) || !preg_match('#^https?://#i', $baseUrl)) {
            $this->recordAttempt($email, $action, false, 'mail_unconfigured');
            return false;
        }

        $fromName = trim((string) ($this->config['from_name'] ?? 'AIMS Nigeria'));
        $fromName = preg_replace('/[\r\n]+/', ' ', $fromName) ?: 'AIMS Nigeria';
        $url = $baseUrl . $path;
        $body = sprintf(
            "%s:\n\n%s\n\nThis link expires at %s UTC and can be used only once. If you did not request this, no action is required.\n",
            $action,
            $url,
            $expiresAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        );
        $headers = [
            'From: ' . $fromName . ' <' . $fromAddress . '>',
            'Content-Type: text/plain; charset=UTF-8',
            'X-Auto-Response-Suppress: All',
        ];

        $sent = mail($email, $subject, $body, implode("\r\n", $headers));
        if (!$sent) {
            $this->logger->error('Authentication email delivery failed.', [
                'recipient_digest' => hash('sha256', mb_strtolower($email)),
                'message_type' => $action,
            ]);
        }
        $this->recordAttempt($email, $action, $sent, $sent ? null : 'provider_rejected');

        return $sent;
    }

    private function recordAttempt(string $email, string $action, bool $sent, ?string $errorCode): void
    {
        if ($this->database === null) return;
        try {
            $db = $this->database->get();
            $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s.u');
            $eventType = $action === 'Verify your email address' ? 'email.verification' : 'email.password_reset';
            $dedupe = hash('sha256', $eventType . '|' . mb_strtolower($email) . '|' . $now);
            $db->prepare("INSERT INTO notification_outbox (public_id,recipient_email,event_type,category,title,body,send_in_app,send_email,send_sms,deduplication_key_hash,status,attempt_count,available_at,processed_at,last_error,created_at,updated_at) VALUES (:public_id,:email,:event,'security',:title,'Authentication email delivery attempt.',0,1,0,:dedupe,:status,1,:now,:processed,:error,:now,:now)")->execute(['public_id'=>Security::uuidV4(),'email'=>mb_strtolower($email),'event'=>$eventType,'title'=>$action,'dedupe'=>$dedupe,'status'=>$sent?'completed':'dead','now'=>$now,'processed'=>$sent?$now:null,'error'=>$errorCode]);
            $outbox=(int)$db->lastInsertId();
            $db->prepare("INSERT INTO notification_deliveries (notification_outbox_id,channel,status,attempt_number,error_code,attempted_at,sent_at) VALUES (:outbox,'email',:status,1,:error,:now,:sent)")->execute(['outbox'=>$outbox,'status'=>$sent?'sent':'failed','error'=>$errorCode,'now'=>$now,'sent'=>$sent?$now:null]);
        } catch (\Throwable $exception) {
            $this->logger->error('Authentication email attempt logging failed.', ['recipient_digest'=>hash('sha256',mb_strtolower($email))]);
        }
    }
}
