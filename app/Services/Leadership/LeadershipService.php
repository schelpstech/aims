<?php

declare(strict_types=1);

namespace App\Services\Leadership;

use App\Logging\Logger;
use App\Models\LeadershipAssignment;
use App\Models\LeadershipGroup;
use App\Models\LeadershipPosition;
use App\Models\Person;
use Throwable;

final class LeadershipService implements LeadershipDirectoryInterface
{
    /** @param list<string> $fallbackGroups */
    public function __construct(
        private readonly LeadershipRepositoryInterface $repository,
        private readonly Logger $logger,
        private readonly array $fallbackGroups = [],
    ) {
    }

    public function directory(): array
    {
        try {
            return [
                'available' => true,
                'groups' => $this->buildGroups($this->repository->publicDirectoryRows()),
            ];
        } catch (Throwable $exception) {
            $this->logger->error('Leadership directory data source is unavailable.', [
                'exception' => $exception,
            ]);

            return [
                'available' => false,
                'groups' => $this->fallbackDirectory(),
            ];
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function buildGroups(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $groupId = (int) ($row['group_id'] ?? 0);
            $groupName = trim((string) ($row['group_name'] ?? ''));
            if ($groupId < 1 || $groupName === '') {
                continue;
            }

            if (!isset($groups[$groupId])) {
                $group = new LeadershipGroup([
                    'id' => $groupId,
                    'name' => $groupName,
                    'slug' => (string) ($row['group_slug'] ?? ''),
                    'description' => $row['group_description'] ?? null,
                    'display_order' => (int) ($row['group_display_order'] ?? 0),
                ]);
                $groups[$groupId] = $group->publicGroup();
            }

            $personPublicId = trim((string) ($row['person_public_id'] ?? ''));
            $personName = trim((string) ($row['person_name'] ?? ''));
            $positionName = trim((string) ($row['position_name'] ?? ''));
            if ($personPublicId === '' || $personName === '' || $positionName === '') {
                continue;
            }

            $person = new Person([
                'public_id' => $personPublicId,
                'full_name' => $personName,
                'title' => $row['person_title'] ?? null,
                'qualifications' => $row['person_qualifications'] ?? null,
                'biography' => $row['person_biography'] ?? null,
                'photo' => $this->safePhotoPath($row['person_photo_path'] ?? null),
                'professional_area' => $row['person_professional_area'] ?? null,
                'email' => $this->safeEmail($row['person_email'] ?? null),
                'linkedin' => $this->safeLinkedIn($row['person_linkedin_url'] ?? null),
                'display_order' => (int) ($row['person_display_order'] ?? 0),
                'active' => true,
            ]);
            $position = new LeadershipPosition([
                'id' => (int) ($row['position_id'] ?? 0),
                'name' => $positionName,
                'description' => $row['position_description'] ?? null,
                'display_order' => (int) ($row['position_display_order'] ?? 0),
            ]);
            $assignment = new LeadershipAssignment([
                'id' => (int) ($row['assignment_id'] ?? 0),
                'display_order' => (int) ($row['assignment_display_order'] ?? 0),
            ]);

            $profile = $person->publicProfile();
            $profile['position'] = $position->publicPosition();
            $profile['assignment'] = $assignment->publicAssignment();
            $groups[$groupId]['members'][] = $profile;
        }

        $directory = array_values($groups);
        usort($directory, static fn (array $left, array $right): int =>
            [$left['display_order'], $left['name']] <=> [$right['display_order'], $right['name']]
        );
        foreach ($directory as &$group) {
            usort($group['members'], static fn (array $left, array $right): int =>
                [
                    $left['position']['display_order'],
                    $left['assignment']['display_order'],
                    $left['display_order'],
                    $left['name'],
                ] <=> [
                    $right['position']['display_order'],
                    $right['assignment']['display_order'],
                    $right['display_order'],
                    $right['name'],
                ]
            );
        }
        unset($group);

        return $directory;
    }

    /** @return list<array<string, mixed>> */
    private function fallbackDirectory(): array
    {
        $groups = [];
        foreach ($this->fallbackGroups as $index => $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $groups[] = [
                'id' => 0,
                'name' => $name,
                'slug' => trim(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? ''), '-'),
                'description' => null,
                'display_order' => ($index + 1) * 10,
                'members' => [],
            ];
        }

        return $groups;
    }

    private function safePhotoPath(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $path = trim($value);
        $decoded = rawurldecode($path);
        if (str_contains($decoded, '..')) {
            return null;
        }

        return preg_match('#^/assets/uploads/leadership/[A-Za-z0-9/_-]+\.(?:jpe?g|png|webp)$#D', $path) === 1
            ? $path
            : null;
    }

    private function safeEmail(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $email = trim($value);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function safeLinkedIn(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $url = trim($value);
        if (!filter_var($url, FILTER_VALIDATE_URL) || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'linkedin.com' || str_ends_with($host, '.linkedin.com') ? $url : null;
    }
}
