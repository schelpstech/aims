<?php

declare(strict_types=1);

namespace App\Services\MemberPortal;

use App\Validation\Validator;
use DateTimeImmutable;

final class MemberPortalService
{
    private const PROFILE_FIELDS = [
        'preferred_name', 'phone', 'alternate_email', 'address', 'city',
        'state_region', 'country', 'professional_area', 'current_role', 'biography',
    ];
    private const SENSITIVE_FIELDS = [
        'member_id', 'public_id', 'user_id', 'membership_number', 'membership_grade',
        'grade_abbreviation', 'status', 'joined_at', 'expires_at',
    ];

    public function __construct(
        private readonly MemberPortalRepositoryInterface $repository,
        private readonly MemberActivitySummaryInterface $activities,
        private readonly Validator $validator,
    ) {
    }

    public function dashboard(int $userId): MemberPortalResult
    {
        if ($userId < 1) {
            return MemberPortalResult::failure(403, 'Member access is required.');
        }
        $member = $this->repository->memberForUser($userId);
        if ($member === null) {
            return MemberPortalResult::failure(403, 'An active or historical member record is required.');
        }
        $memberId = (int) ($member['member_id'] ?? 0);
        if ($memberId < 1) {
            return MemberPortalResult::failure(403, 'Member access is required.');
        }
        $member['name'] = $this->memberName($member);
        $member['renewal_status'] = $this->renewalStatus($member['expires_at'] ?? null);
        unset($member['member_id']);

        $notifications = array_map(function (array $notification): array {
            $url = is_string($notification['action_url'] ?? null) ? trim($notification['action_url']) : '';
            $notification['action_url'] = preg_match('#^/[A-Za-z0-9/_?&=.%+-]*$#D', $url) === 1
                && !str_contains(rawurldecode($url), '..')
                ? $url
                : null;

            return $notification;
        }, $this->repository->recentNotificationsForUser($userId, 5));

        return MemberPortalResult::success([
            'member' => $member,
            'notifications' => $notifications,
            'counts' => $this->activities->counts($memberId),
        ]);
    }

    /** @param array<string, mixed> $input */
    public function updateProfile(int $userId, array $input): MemberPortalResult
    {
        foreach (self::SENSITIVE_FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                return MemberPortalResult::failure(403, 'Sensitive membership fields cannot be changed from the member portal.');
            }
        }
        $rules = [
            'preferred_name' => 'nullable|string|max:120',
            'phone' => 'nullable|string',
            'alternate_email' => 'nullable|email|max:254',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state_region' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'professional_area' => 'nullable|string|max:160',
            'current_role' => 'nullable|string|max:160',
            'biography' => 'nullable|string|max:2000',
        ];
        if (!$this->validator->validate($input, $rules)) {
            return MemberPortalResult::failure(422, 'Review the highlighted profile fields.', $this->validator->errors());
        }
        $profile = [];
        $validated = $this->validator->validated();
        foreach (self::PROFILE_FIELDS as $field) {
            $value = $validated[$field] ?? null;
            $profile[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }
        if (is_string($profile['phone']) && mb_strlen($profile['phone']) > 40) {
            return MemberPortalResult::failure(422, 'Review the highlighted profile fields.', [
                'phone' => ['The phone number must not exceed 40 characters.'],
            ]);
        }

        $member = $this->repository->updateProfileForUser($userId, $profile, new DateTimeImmutable('now'));
        if ($member === null) {
            return MemberPortalResult::failure(403, 'Only an active member may update profile information.');
        }
        unset($member['member_id']);

        return MemberPortalResult::success(['member' => $member], 'Your permitted profile fields were updated.');
    }

    /** @param array<string, mixed> $member */
    private function memberName(array $member): string
    {
        $preferred = trim((string) ($member['preferred_name'] ?? ''));
        if ($preferred !== '') {
            return $preferred;
        }
        $name = trim(implode(' ', array_filter([
            trim((string) ($member['first_name'] ?? '')),
            trim((string) ($member['last_name'] ?? '')),
        ])));

        return $name !== '' ? $name : 'Member';
    }

    private function renewalStatus(mixed $expiresAt): array
    {
        if (!is_string($expiresAt) || trim($expiresAt) === '') {
            return ['code' => 'not_configured', 'label' => 'Not configured', 'date' => null];
        }
        $expiry = DateTimeImmutable::createFromFormat('!Y-m-d', $expiresAt);
        if ($expiry === false) {
            return ['code' => 'not_configured', 'label' => 'Not configured', 'date' => null];
        }
        $today = new DateTimeImmutable('today');
        if ($expiry < $today) {
            return ['code' => 'due', 'label' => 'Renewal due', 'date' => $expiresAt];
        }
        if ($expiry <= $today->modify('+30 days')) {
            return ['code' => 'approaching', 'label' => 'Renewal approaching', 'date' => $expiresAt];
        }

        return ['code' => 'current', 'label' => 'Current', 'date' => $expiresAt];
    }
}
