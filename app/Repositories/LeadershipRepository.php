<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use App\Services\Leadership\LeadershipRepositoryInterface;

final class LeadershipRepository extends Repository implements LeadershipRepositoryInterface
{
    public function __construct(Connection $database)
    {
        parent::__construct($database);
    }

    public function publicDirectoryRows(): array
    {
        $statement = $this->connection()->query(<<<'SQL'
            SELECT
                leadership_group.id AS group_id,
                leadership_group.name AS group_name,
                leadership_group.slug AS group_slug,
                leadership_group.description AS group_description,
                leadership_group.display_order AS group_display_order,
                positions.id AS position_id,
                positions.name AS position_name,
                positions.description AS position_description,
                positions.display_order AS position_display_order,
                assignments.id AS assignment_id,
                assignments.display_order AS assignment_display_order,
                people.public_id AS person_public_id,
                people.full_name AS person_name,
                people.title AS person_title,
                people.qualifications AS person_qualifications,
                people.biography AS person_biography,
                people.photo_path AS person_photo_path,
                people.professional_area AS person_professional_area,
                people.email AS person_email,
                people.linkedin_url AS person_linkedin_url,
                people.display_order AS person_display_order
            FROM leadership_groups AS leadership_group
            LEFT JOIN leadership_positions AS positions
                ON positions.leadership_group_id = leadership_group.id
               AND positions.active = 1
            LEFT JOIN leadership_assignments AS assignments
                ON assignments.leadership_position_id = positions.id
               AND assignments.active = 1
               AND (assignments.starts_at IS NULL OR assignments.starts_at <= CURRENT_DATE)
               AND (assignments.ends_at IS NULL OR assignments.ends_at >= CURRENT_DATE)
            LEFT JOIN people
                ON people.id = assignments.person_id
               AND people.active = 1
               AND people.deleted_at IS NULL
            WHERE leadership_group.active = 1
            ORDER BY
                leadership_group.display_order,
                leadership_group.name,
                positions.display_order,
                positions.name,
                assignments.display_order,
                people.display_order,
                people.full_name
            SQL);

        return $statement->fetchAll();
    }
}
