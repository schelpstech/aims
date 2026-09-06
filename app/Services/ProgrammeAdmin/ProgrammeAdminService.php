<?php

declare(strict_types=1);

namespace App\Services\ProgrammeAdmin;

use App\Authorization\PermissionCheckerInterface;
use DomainException;
use Throwable;

final class ProgrammeAdminService
{
    public function __construct(private readonly ProgrammeAdminRepositoryInterface $repository, private readonly PermissionCheckerInterface $permissions) {}

    public function dashboard(int $actor): ProgrammeAdminResult
    {
        if (!$this->allowed($actor, 'programme.view')) return ProgrammeAdminResult::failure(403, 'You are not authorized to view programmes.');
        return ProgrammeAdminResult::success('Programme administration loaded.', $this->repository->dashboard());
    }

    /** @param array<string, mixed> $input */
    public function saveProgramme(int $actor, ?string $publicId, array $input): ProgrammeAdminResult
    {
        $permission = $publicId === null ? 'programme.create' : 'programme.update';
        if (!$this->allowed($actor, $permission)) return ProgrammeAdminResult::failure(403, 'You are not authorized to save this programme.');
        if ($publicId !== null && !$this->uuid($publicId)) return ProgrammeAdminResult::failure(404, 'Programme not found.');
        $errors = [];
        $name = $this->text($input, 'name'); $code = strtoupper($this->text($input, 'code')); $type = $this->text($input, 'programme_type');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 200) $errors[] = 'Programme name must be between 2 and 200 characters.';
        if (preg_match('/^[A-Z0-9._-]{2,60}$/D', $code) !== 1) $errors[] = 'Programme code must use 2-60 letters, numbers, dots, underscores or hyphens.';
        if (!$this->uuid($type)) $errors[] = 'Select a valid programme type.';
        $status = $this->text($input, 'status'); if (!in_array($status, ['draft', 'published', 'archived'], true)) $errors[] = 'Select a valid programme status.';
        $areas = $input['areas'] ?? []; $areas = is_array($areas) ? array_values(array_unique(array_filter($areas, fn ($id): bool => is_string($id) && $this->uuid($id)))) : [];
        foreach (['description' => 10000, 'duration' => 120, 'entry_requirements' => 10000, 'delivery_mode' => 120] as $field => $limit) if (mb_strlen($this->text($input, $field)) > $limit) $errors[] = ucfirst(str_replace('_', ' ', $field)) . " must not exceed {$limit} characters.";
        $applicationFee = $this->money($input['application_fee'] ?? null, 'Application fee', $errors);
        $tuitionFee = $this->money($input['tuition_fee'] ?? null, 'Tuition fee', $errors);
        $currency = strtoupper($this->text($input, 'fee_currency'));
        if (($applicationFee !== null || $tuitionFee !== null) && preg_match('/^[A-Z]{3}$/D', $currency) !== 1) $errors[] = 'A three-letter currency is required when a fee is supplied.';
        if ($errors !== []) return ProgrammeAdminResult::failure(422, implode(' ', $errors));
        try {
            $id = $this->repository->saveProgramme($actor, $publicId, [
                'name' => $name, 'code' => $code, 'slug' => $this->slug($name), 'programme_type' => $type,
                'description' => $this->nullable($input, 'description'), 'duration' => $this->nullable($input, 'duration'),
                'entry_requirements' => $this->nullable($input, 'entry_requirements'), 'delivery_mode' => $this->nullable($input, 'delivery_mode'),
                'application_fee' => $applicationFee, 'tuition_fee' => $tuitionFee, 'fee_currency' => ($applicationFee !== null || $tuitionFee !== null) ? $currency : null,
                'status' => $status, 'display_order' => max(0, min(65535, (int) ($input['display_order'] ?? 0))), 'areas' => $areas,
            ]);
            return ProgrammeAdminResult::success($publicId === null ? 'Programme created.' : 'Programme updated.', ['public_id' => $id]);
        } catch (DomainException $e) { return ProgrammeAdminResult::failure(409, $e->getMessage()); }
    }

    public function archiveProgramme(int $actor, string $publicId): ProgrammeAdminResult
    {
        if (!$this->allowed($actor, 'programme.delete')) return ProgrammeAdminResult::failure(403, 'You are not authorized to archive programmes.');
        if (!$this->uuid($publicId)) return ProgrammeAdminResult::failure(404, 'Programme not found.');
        try { $this->repository->archiveProgramme($actor, $publicId); return ProgrammeAdminResult::success('Programme archived.'); }
        catch (DomainException $e) { return ProgrammeAdminResult::failure(404, $e->getMessage()); }
    }

    /** @param array<string, mixed> $input */
    public function saveArea(int $actor, ?string $publicId, array $input): ProgrammeAdminResult
    {
        $permission = $publicId === null ? 'programme.create' : 'programme.update';
        if (!$this->allowed($actor, $permission)) return ProgrammeAdminResult::failure(403, 'You are not authorized to save professional areas.');
        if ($publicId !== null && !$this->uuid($publicId)) return ProgrammeAdminResult::failure(404, 'Professional area not found.');
        $name = $this->text($input, 'name'); $code = strtoupper($this->text($input, 'code'));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 160) return ProgrammeAdminResult::failure(422, 'Area name must be between 2 and 160 characters.');
        if ($code !== '' && preg_match('/^[A-Z0-9._-]{2,40}$/D', $code) !== 1) return ProgrammeAdminResult::failure(422, 'Area code format is invalid.');
        if (mb_strlen($this->text($input, 'description')) > 10000) return ProgrammeAdminResult::failure(422, 'Area description is too long.');
        try { $id = $this->repository->saveArea($actor, $publicId, ['name' => $name, 'code' => $code ?: null, 'slug' => $this->slug($name), 'description' => $this->nullable($input, 'description'), 'display_order' => max(0, min(65535, (int) ($input['display_order'] ?? 0))), 'active' => isset($input['active']) ? 1 : 0]); return ProgrammeAdminResult::success($publicId === null ? 'Professional area created.' : 'Professional area updated.', ['public_id' => $id]); }
        catch (DomainException $e) { return ProgrammeAdminResult::failure(409, $e->getMessage()); }
    }

    public function archiveArea(int $actor, string $publicId): ProgrammeAdminResult
    {
        if (!$this->allowed($actor, 'programme.delete')) return ProgrammeAdminResult::failure(403, 'You are not authorized to archive professional areas.');
        if (!$this->uuid($publicId)) return ProgrammeAdminResult::failure(404, 'Professional area not found.');
        try { $this->repository->archiveArea($actor, $publicId); return ProgrammeAdminResult::success('Professional area archived.'); } catch (DomainException $e) { return ProgrammeAdminResult::failure(409, $e->getMessage()); }
    }

    /** @param array<string, mixed> $input */
    public function assignCoordinator(int $actor, array $input): ProgrammeAdminResult
    {
        if (!$this->allowed($actor, 'programme.assign_coordinators')) return ProgrammeAdminResult::failure(403, 'You are not authorized to assign coordinators.');
        $person = $this->text($input, 'person'); $programme = $this->text($input, 'programme'); $area = $this->text($input, 'area');
        if (!$this->uuid($person) || ($programme === '' && $area === '') || ($programme !== '' && !$this->uuid($programme)) || ($area !== '' && !$this->uuid($area))) return ProgrammeAdminResult::failure(422, 'Select an eligible coordinator and at least one valid target.');
        $start = $this->date($input['starts_at'] ?? null); $end = $this->date($input['ends_at'] ?? null);
        if (($input['starts_at'] ?? '') !== '' && $start === null || ($input['ends_at'] ?? '') !== '' && $end === null || ($start && $end && $end < $start)) return ProgrammeAdminResult::failure(422, 'Coordinator assignment dates are invalid.');
        try { $this->repository->assignCoordinator($actor, ['person' => $person, 'programme' => $programme ?: null, 'area' => $area ?: null, 'role_title' => $this->nullable($input, 'role_title'), 'starts_at' => $start, 'ends_at' => $end, 'display_order' => max(0, min(65535, (int) ($input['display_order'] ?? 0)))]); return ProgrammeAdminResult::success('Coordinator assigned.'); } catch (DomainException $e) { return ProgrammeAdminResult::failure(409, $e->getMessage()); }
    }

    public function removeCoordinator(int $actor, string $publicId): ProgrammeAdminResult
    {
        if (!$this->allowed($actor, 'programme.assign_coordinators')) return ProgrammeAdminResult::failure(403, 'You are not authorized to remove coordinators.');
        if (!$this->uuid($publicId)) return ProgrammeAdminResult::failure(404, 'Coordinator assignment not found.');
        try { $this->repository->removeCoordinator($actor, $publicId); return ProgrammeAdminResult::success('Coordinator assignment removed.'); } catch (DomainException $e) { return ProgrammeAdminResult::failure(404, $e->getMessage()); }
    }

    private function allowed(int $actor, string $permission): bool { return $actor > 0 && $this->permissions->allows($actor, $permission); }
    /** @param array<string, mixed> $input */ private function text(array $input, string $key): string { return is_string($input[$key] ?? null) ? trim($input[$key]) : ''; }
    /** @param array<string, mixed> $input */ private function nullable(array $input, string $key): ?string { $value = $this->text($input, $key); return $value === '' ? null : $value; }
    private function uuid(string $id): bool { return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di', $id) === 1; }
    private function slug(string $name): string { return trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? ''), '-'); }
    /** @param list<string> $errors */ private function money(mixed $value, string $label, array &$errors): ?string { if ($value === null || trim((string) $value) === '') return null; if (!is_numeric($value) || (float) $value < 0 || (float) $value > 9999999999.99) { $errors[] = $label . ' must be a valid non-negative amount.'; return null; } return number_format((float) $value, 2, '.', ''); }
    private function date(mixed $value): ?string { $value = is_string($value) ? trim($value) : ''; if ($value === '') return null; $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value); return $date && $date->format('Y-m-d') === $value ? $value : null; }
}
