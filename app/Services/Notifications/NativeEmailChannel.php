<?php
declare(strict_types=1);
namespace App\Services\Notifications;
final class NativeEmailChannel implements EmailChannelInterface
{
    /** @param array<string,mixed> $config */ public function __construct(private readonly array $config){}
    public function send(string $recipient,string $subject,string $body):bool
    {
        $from=trim((string)($this->config['from_address']??''));
        if (!(bool)($this->config['enabled']??false)||!filter_var($recipient,FILTER_VALIDATE_EMAIL)||!filter_var($from,FILTER_VALIDATE_EMAIL)) return false;
        $name=preg_replace('/[\r\n]+/',' ',trim((string)($this->config['from_name']??'AIMS Nigeria')))?:'AIMS Nigeria';
        $subject=preg_replace('/[\r\n]+/',' ',trim($subject))?:'AIMS Nigeria notification';
        return mail($recipient,$subject,$body,implode("\r\n",['From: '.$name.' <'.$from.'>','Content-Type: text/plain; charset=UTF-8','X-Auto-Response-Suppress: All']));
    }
}
