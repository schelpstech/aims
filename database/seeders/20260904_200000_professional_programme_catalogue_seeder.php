<?php

declare(strict_types=1);

use App\Database\Seeder;

return new class implements Seeder {
    public function run(\PDO $database): void
    {
        $area = $database->prepare(<<<'SQL'
            INSERT INTO professional_areas (public_id, name, slug, display_order, active)
            VALUES (:public_id, :name, :slug, :display_order, 1)
            ON DUPLICATE KEY UPDATE name = VALUES(name), slug = VALUES(slug), display_order = VALUES(display_order), active = 1, deleted_at = NULL
            SQL);
        $areas = [
            ['public_id' => '165918a8-c269-4941-bf2a-3eb3635743bf', 'name' => 'General Management', 'slug' => 'general-management', 'display_order' => 10],
            ['public_id' => 'f722d13f-05ca-4fa2-a663-31346055bdd2', 'name' => 'Marketing', 'slug' => 'marketing', 'display_order' => 20],
            ['public_id' => '759485d3-d37d-4eb0-8cb6-d44c64fe62f6', 'name' => 'Human Resources', 'slug' => 'human-resources', 'display_order' => 30],
            ['public_id' => '4a2fc608-315b-4ef9-b8a9-faad7ab971a0', 'name' => 'Finance', 'slug' => 'finance', 'display_order' => 40],
            ['public_id' => '6db5a501-d8d7-4e05-96af-ebd323f3c724', 'name' => 'Operations', 'slug' => 'operations', 'display_order' => 50],
            ['public_id' => 'f619418a-bbde-43f4-9dc2-83e2988ef9c1', 'name' => 'ICT', 'slug' => 'ict', 'display_order' => 60],
        ];
        foreach ($areas as $record) { $area->execute($record); }

        $type = $database->prepare(<<<'SQL'
            INSERT INTO programme_types (public_id, name, slug, display_order, active)
            VALUES (:public_id, :name, :slug, :display_order, 1)
            ON DUPLICATE KEY UPDATE name = VALUES(name), slug = VALUES(slug), display_order = VALUES(display_order), active = 1
            SQL);
        $types = [
            ['public_id' => 'ae0df96e-d5db-49c8-9e4b-dcd13673fa28', 'name' => 'Diploma', 'slug' => 'diploma', 'display_order' => 10],
            ['public_id' => '6c211caa-3273-4db7-8aae-b323b31ee079', 'name' => 'Higher Diploma', 'slug' => 'higher-diploma', 'display_order' => 20],
            ['public_id' => '492b1d92-f912-40cd-942b-e6bf12db1070', 'name' => 'Graduate Certificate', 'slug' => 'graduate-certificate', 'display_order' => 30],
            ['public_id' => 'a24e27fb-04b3-45cd-91c6-03f37184a93c', 'name' => 'Post Graduate Diploma', 'slug' => 'post-graduate-diploma', 'display_order' => 40],
        ];
        foreach ($types as $record) { $type->execute($record); }
    }
};
