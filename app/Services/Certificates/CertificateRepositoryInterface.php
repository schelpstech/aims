<?php

declare(strict_types=1);

namespace App\Services\Certificates;

use DateTimeImmutable;

interface CertificateRepositoryInterface
{
    /** @return array<string,mixed>|null */public function verifyMembership(string$number,string$lookupHash,string$ip,string$userAgent,DateTimeImmutable$now):?array;
    /** @return array<string,mixed>|null */public function verifyCertificate(string$method,string$value,?string$publicId,string$tokenHash,string$lookupHash,string$ip,string$userAgent,DateTimeImmutable$now):?array;
    /** @return array<string,mixed> */public function adminCatalogue():array;
    public function saveType(int$actor,?string$publicId,array$data,DateTimeImmutable$now):string;
    /** @return array<string,mixed> */public function issue(int$actor,array$data,string$publicId,string$number,string$tokenHash,DateTimeImmutable$now):array;
    /** @return array<string,mixed> */public function revoke(int$actor,string$publicId,string$reason,DateTimeImmutable$now):array;
    /** @return list<array<string,mixed>> */public function certificatesForUser(int$userId):array;
}
