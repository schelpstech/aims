<?php

declare(strict_types=1);

use App\Database\Seeder;

return new class implements Seeder {
    public function run(\PDO $database): void
    {
        $statement = $database->prepare(<<<'SQL'
            INSERT INTO leadership_groups (name, slug, display_order, active)
            VALUES (:name, :slug, :display_order, 1)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                display_order = VALUES(display_order),
                active = 1
            SQL);

        $groups = [
            ['name' => 'Advisory Board', 'slug' => 'advisory-board', 'display_order' => 10],
            ['name' => 'Governing Board', 'slug' => 'governing-board', 'display_order' => 20],
            ['name' => 'Management Team', 'slug' => 'management-team', 'display_order' => 30],
            ['name' => 'Programme Coordinators', 'slug' => 'programme-coordinators', 'display_order' => 40],
        ];

        foreach ($groups as $group) {
            $statement->execute($group);
        }
    }
};
