<?php declare(strict_types=1);
namespace App\Services\Events;
use DateTimeImmutable;
interface EventRepositoryInterface {
    public function publicEvents():array; public function publicEvent(string $slug):?array; public function memberRegistrations(int $userId):array;
    public function register(?int $userId,string $eventPublicId,array $data,string $reference,DateTimeImmutable $now):array;
    public function adminDashboard():array; public function attendees(string $eventPublicId):?array;
    public function saveEvent(int $actor,?string $publicId,array $data):string; public function cancelEvent(int $actor,string $publicId,DateTimeImmutable $now):void;
    public function cancelRegistration(int $actor,string $eventPublicId,string $registrationPublicId,DateTimeImmutable $now):void; public function recordAttendance(int $actor,string $eventPublicId,string $registrationPublicId,DateTimeImmutable $now):array;
}
