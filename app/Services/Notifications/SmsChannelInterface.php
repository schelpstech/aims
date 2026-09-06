<?php
declare(strict_types=1);
namespace App\Services\Notifications;
interface SmsChannelInterface { public function isAvailable(): bool; public function send(string $recipient, string $message): bool; }
