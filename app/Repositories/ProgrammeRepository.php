<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use App\Services\Programmes\ProgrammeRepositoryInterface;
use PDO;

final class ProgrammeRepository extends Repository implements ProgrammeRepositoryInterface
{
    public function __construct(Connection $database)
    {
        parent::__construct($database);
    }

    public function programmeTypes(): array
    {
        return $this->connection()->query(<<<'SQL'
            SELECT public_id, name, slug, description, display_order
            FROM programme_types
            WHERE active = 1
            ORDER BY display_order, name
            SQL)->fetchAll();
    }

    public function programmeRows(?string $slug = null): array
    {
        $sql = <<<'SQL'
            SELECT programmes.public_id, programmes.code, programmes.name, programmes.slug,
                   programmes.description, programmes.duration, programmes.entry_requirements,
                   programmes.delivery_mode, programmes.application_fee, programmes.tuition_fee,
                   programmes.fee_currency, programmes.display_order,
                   types.name AS type_name, types.slug AS type_slug,
                   areas.public_id AS area_public_id, areas.name AS area_name, areas.slug AS area_slug,
                   programme_areas.is_primary AS area_is_primary,
                   people.public_id AS coordinator_public_id, people.full_name AS coordinator_name,
                   people.title AS coordinator_title, assignments.role_title AS coordinator_role
            FROM programmes
            INNER JOIN programme_types AS types ON types.id = programmes.programme_type_id AND types.active = 1
            LEFT JOIN programme_areas ON programme_areas.programme_id = programmes.id
            LEFT JOIN professional_areas AS areas
              ON areas.id = programme_areas.professional_area_id AND areas.active = 1 AND areas.deleted_at IS NULL
            LEFT JOIN coordinator_assignments AS assignments
              ON assignments.programme_id = programmes.id AND assignments.active = 1
             AND (assignments.starts_at IS NULL OR assignments.starts_at <= CURRENT_DATE)
             AND (assignments.ends_at IS NULL OR assignments.ends_at >= CURRENT_DATE)
            LEFT JOIN people ON people.id = assignments.person_id AND people.active = 1 AND people.deleted_at IS NULL
            WHERE programmes.status = 'published' AND programmes.deleted_at IS NULL
            SQL;
        $parameters = [];
        if ($slug !== null) {
            $sql .= ' AND programmes.slug = :slug';
            $parameters['slug'] = $slug;
        }
        $sql .= ' ORDER BY programmes.display_order, programmes.name, programme_areas.is_primary DESC, programme_areas.display_order, assignments.display_order, people.full_name';
        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll();
    }

    public function areaRows(?string $slug = null): array
    {
        $sql = <<<'SQL'
            SELECT areas.public_id, areas.code, areas.name, areas.slug, areas.description, areas.display_order,
                   programmes.public_id AS programme_public_id, programmes.code AS programme_code,
                   programmes.name AS programme_name, programmes.slug AS programme_slug,
                   types.name AS programme_type,
                   people.public_id AS coordinator_public_id, people.full_name AS coordinator_name,
                   people.title AS coordinator_title, assignments.role_title AS coordinator_role
            FROM professional_areas AS areas
            LEFT JOIN programme_areas ON programme_areas.professional_area_id = areas.id
            LEFT JOIN programmes
              ON programmes.id = programme_areas.programme_id AND programmes.status = 'published' AND programmes.deleted_at IS NULL
            LEFT JOIN programme_types AS types ON types.id = programmes.programme_type_id
            LEFT JOIN coordinator_assignments AS assignments
              ON assignments.professional_area_id = areas.id AND assignments.active = 1
             AND (assignments.starts_at IS NULL OR assignments.starts_at <= CURRENT_DATE)
             AND (assignments.ends_at IS NULL OR assignments.ends_at >= CURRENT_DATE)
            LEFT JOIN people ON people.id = assignments.person_id AND people.active = 1 AND people.deleted_at IS NULL
            WHERE areas.active = 1 AND areas.deleted_at IS NULL
            SQL;
        $parameters = [];
        if ($slug !== null) {
            $sql .= ' AND areas.slug = :slug';
            $parameters['slug'] = $slug;
        }
        $sql .= ' ORDER BY areas.display_order, areas.name, programme_areas.is_primary DESC, programme_areas.display_order, programmes.name, assignments.display_order, people.full_name';
        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll();
    }
}
