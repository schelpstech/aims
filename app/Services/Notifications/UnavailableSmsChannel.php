<?php
declare(strict_types=1);
namespace App\Services\Notifications;
final class UnavailableSmsChannel implements SmsChannelInterface { public function isAvailable(): bool{return false;} public function send(string $recipient,string $message):bool{return false;} }
