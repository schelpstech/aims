<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;

final class CertificateTokenSigner
{
    public function __construct(private readonly string$key){}
    public function available():bool{return strlen($this->key)>=32;}
    public function issue(string$publicId):string{if(!$this->available())throw new RuntimeException('Certificate signing is not configured.');$signature=hash_hmac('sha256','certificate|'.strtolower($publicId),$this->key,true);return'v1.'.strtolower($publicId).'.'.rtrim(strtr(base64_encode($signature),'+/','-_'),'=');}
    public function publicId(string$token):?string{if(!$this->available()||preg_match('/^v1\.([a-f0-9-]{36})\.([A-Za-z0-9_-]{43})$/D',trim($token),$m)!==1)return null;$expected=$this->issue($m[1]);return hash_equals($expected,trim($token))?$m[1]:null;}
    public function digest(string$token):string{return hash('sha256',trim($token));}
}
