<?php
declare(strict_types=1);
namespace App\Services\Notifications;
interface EmailChannelInterface { public function send(string $recipient, string $subject, string $body): bool; }
