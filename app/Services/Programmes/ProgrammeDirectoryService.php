<?php

declare(strict_types=1);

namespace App\Services\Programmes;

use App\Logging\Logger;
use Throwable;

final class ProgrammeDirectoryService implements ProgrammeDirectoryInterface
{
    public function __construct(
        private readonly ProgrammeRepositoryInterface $repository,
        private readonly Logger $logger,
    ) {
    }

    public function catalogue(): array
    {
        try {
            return ['available' => true, 'types' => $this->repository->programmeTypes(), 'programmes' => $this->buildProgrammes($this->repository->programmeRows())];
        } catch (Throwable $exception) {
            $this->logger->error('Programme catalogue data source is unavailable.', ['exception' => $exception]);
            return ['available' => false, 'types' => [], 'programmes' => []];
        }
    }

    public function areas(): array
    {
        try {
            return ['available' => true, 'areas' => $this->buildAreas($this->repository->areaRows())];
        } catch (Throwable $exception) {
            $this->logger->error('Professional area data source is unavailable.', ['exception' => $exception]);
            return ['available' => false, 'areas' => []];
        }
    }

    public function programme(string $slug): ?array
    {
        try {
            $items = $this->buildProgrammes($this->repository->programmeRows($slug));
            return $items[0] ?? null;
        } catch (Throwable $exception) {
            $this->logger->error('Programme detail data source is unavailable.', ['exception' => $exception]);
            return null;
        }
    }

    public function area(string $slug): ?array
    {
        try {
            $items = $this->buildAreas($this->repository->areaRows($slug));
            return $items[0] ?? null;
        } catch (Throwable $exception) {
            $this->logger->error('Professional area detail data source is unavailable.', ['exception' => $exception]);
            return null;
        }
    }

    /** @param list<array<string, mixed>> $rows
     *  @return list<array<string, mixed>>
     */
    private function buildProgrammes(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $id = (string) ($row['public_id'] ?? '');
            if ($id === '') {
                continue;
            }
            $items[$id] ??= [
                'public_id' => $id, 'code' => (string) $row['code'], 'name' => (string) $row['name'],
                'slug' => (string) $row['slug'], 'description' => $row['description'], 'duration' => $row['duration'],
                'entry_requirements' => $row['entry_requirements'], 'delivery_mode' => $row['delivery_mode'],
                'application_fee' => $row['application_fee'], 'tuition_fee' => $row['tuition_fee'],
                'fee_currency' => $row['fee_currency'], 'type' => ['name' => (string) $row['type_name'], 'slug' => (string) $row['type_slug']],
                'areas' => [], 'coordinators' => [],
            ];
            $areaId = (string) ($row['area_public_id'] ?? '');
            if ($areaId !== '') {
                $items[$id]['areas'][$areaId] = ['name' => (string) $row['area_name'], 'slug' => (string) $row['area_slug'], 'primary' => (bool) $row['area_is_primary']];
            }
            $personId = (string) ($row['coordinator_public_id'] ?? '');
            if ($personId !== '') {
                $items[$id]['coordinators'][$personId] = $this->coordinator($row);
            }
        }
        foreach ($items as &$item) {
            $item['areas'] = array_values($item['areas']);
            $item['coordinators'] = array_values($item['coordinators']);
        }
        unset($item);
        return array_values($items);
    }

    /** @param list<array<string, mixed>> $rows
     *  @return list<array<string, mixed>>
     */
    private function buildAreas(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $id = (string) ($row['public_id'] ?? '');
            if ($id === '') {
                continue;
            }
            $items[$id] ??= ['public_id' => $id, 'code' => $row['code'], 'name' => (string) $row['name'], 'slug' => (string) $row['slug'], 'description' => $row['description'], 'programmes' => [], 'coordinators' => []];
            $programmeId = (string) ($row['programme_public_id'] ?? '');
            if ($programmeId !== '') {
                $items[$id]['programmes'][$programmeId] = ['code' => (string) $row['programme_code'], 'name' => (string) $row['programme_name'], 'slug' => (string) $row['programme_slug'], 'type' => (string) $row['programme_type']];
            }
            $personId = (string) ($row['coordinator_public_id'] ?? '');
            if ($personId !== '') {
                $items[$id]['coordinators'][$personId] = $this->coordinator($row);
            }
        }
        foreach ($items as &$item) {
            $item['programmes'] = array_values($item['programmes']);
            $item['coordinators'] = array_values($item['coordinators']);
        }
        unset($item);
        return array_values($items);
    }

    /** @param array<string, mixed> $row
     *  @return array<string, string|null>
     */
    private function coordinator(array $row): array
    {
        return ['name' => (string) $row['coordinator_name'], 'title' => $row['coordinator_title'], 'role' => $row['coordinator_role']];
    }
}
