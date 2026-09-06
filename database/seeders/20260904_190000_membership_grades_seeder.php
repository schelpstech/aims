<?php

declare(strict_types=1);

use App\Database\Seeder;

return new class implements Seeder {
    public function run(\PDO $database): void
    {
        $statement = $database->prepare(<<<'SQL'
            INSERT INTO membership_grades (public_id, name, display_order, active)
            VALUES (:public_id, :name, :display_order, 1)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                display_order = VALUES(display_order),
                active = 1
            SQL);

        $grades = [
            ['public_id' => '5c168e52-935b-4816-8e02-4faf89ebebaf', 'name' => 'Fellow', 'display_order' => 10],
            ['public_id' => 'ab596e3e-66b7-47d9-a110-4eaf756b774d', 'name' => 'Member', 'display_order' => 20],
            ['public_id' => '6fd2591f-5f48-48c1-9dd0-8c9f1a40ead3', 'name' => 'Associate', 'display_order' => 30],
        ];

        foreach ($grades as $grade) {
            $statement->execute($grade);
        }
    }
};
