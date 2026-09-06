<?php

declare(strict_types=1);

use App\Database\Seeder;

return new class implements Seeder {
    public function run(\PDO $database): void
    {
        $personStatement = $database->prepare(<<<'SQL'
            INSERT INTO people (public_id, full_name, title, display_order, active)
            VALUES (:public_id, :full_name, :title, :display_order, 1)
            ON DUPLICATE KEY UPDATE
                full_name = VALUES(full_name),
                display_order = VALUES(display_order),
                active = 1
            SQL);

        $people = [
            ['public_id' => '2df7567e-3d2a-4fb9-9023-ec0a7f4cb3bb', 'full_name' => 'Segun Folorunso', 'title' => 'Prof', 'display_order' => 10],
            ['public_id' => '03adff46-f795-4fe4-9840-afa4515c4474', 'full_name' => 'T.A Okeowo', 'title' => 'Dr', 'display_order' => 20],
            ['public_id' => '92aca025-9503-459c-8a0f-c47473769609', 'full_name' => 'Adeyinka Bakare', 'title' => 'Dr', 'display_order' => 30],
            ['public_id' => '184910c2-397c-4c58-9356-30dfb581b0d8', 'full_name' => 'Ezekiel Odinmayo', 'title' => 'Engr Dr', 'display_order' => 40],
            ['public_id' => 'eef471e3-6b7b-42a5-88bd-5fc88befb46a', 'full_name' => 'Durosimi Adekunle', 'title' => 'Mr', 'display_order' => 50],
            ['public_id' => 'f781a35d-ada2-4bf6-bca6-39ddd5e03730', 'full_name' => 'Adedeji Oyenuga', 'title' => 'Prof', 'display_order' => 60],
            ['public_id' => '5430c743-a42a-4132-9006-e0afa8bcba81', 'full_name' => 'Samuel Adekunle', 'title' => 'Dr', 'display_order' => 70],
            ['public_id' => '9e028a37-6b21-4435-979a-b039c716fba9', 'full_name' => 'Tayo Olatunbosun', 'title' => 'Mr', 'display_order' => 80],
            ['public_id' => 'bc2a7663-bcd0-4afa-be76-2aaf3ac91816', 'full_name' => 'Sunday Fiola', 'title' => 'Barr', 'display_order' => 90],
            ['public_id' => '091e5720-b4cb-43bf-90d4-f53fbeedbee2', 'full_name' => 'Akeem Bayewu', 'title' => 'Engr', 'display_order' => 100],
            ['public_id' => '58779074-0e2f-4768-ab38-38688312f440', 'full_name' => 'Adeosun Olayiwola', 'title' => 'Dr', 'display_order' => 110],
            ['public_id' => '6541dd47-ec81-407b-b439-cdddd940ee91', 'full_name' => 'Amusa Nojimu Adetunji', 'title' => 'Prof', 'display_order' => 120],
            ['public_id' => 'f9a441f3-9754-4383-a28a-ebf5745a41e3', 'full_name' => 'Lucky', 'title' => 'Dr', 'display_order' => 130],
            ['public_id' => '725dff8a-96da-4e1d-92cf-bbbc2d24eb7f', 'full_name' => 'Awe O. J', 'title' => 'Mr', 'display_order' => 140],
            ['public_id' => '019d8d8e-12c8-4678-b155-0f6167f08df9', 'full_name' => 'Favour Adekunle', 'title' => null, 'display_order' => 150],
            ['public_id' => '285f52fb-88ca-4ee1-8db2-383a02ed6a57', 'full_name' => 'Faithful', 'title' => 'Mr', 'display_order' => 160],
            ['public_id' => '3b2197e3-a476-4be3-b0d5-005c09bc51d8', 'full_name' => 'Tijani Ademola', 'title' => 'Mr', 'display_order' => 170],
            ['public_id' => '848dd552-cf99-424c-aa9e-9c60f7a50755', 'full_name' => 'Dandy Makpah', 'title' => 'Mr', 'display_order' => 180],
            ['public_id' => 'd2d0082b-e224-4c11-91df-6282e107caeb', 'full_name' => 'Koleosho', 'title' => 'Mr', 'display_order' => 190],
            ['public_id' => 'fb0968af-78e3-45ba-8981-170a59fc367f', 'full_name' => 'Emmanuel Akinsade', 'title' => 'Mr', 'display_order' => 200],
            ['public_id' => 'e686a23f-1f6b-4220-ac3e-56e54588b16e', 'full_name' => 'Wasiu Olalekan', 'title' => 'Mr', 'display_order' => 210],
            ['public_id' => '322e815e-246d-4230-b562-890e7e516ffd', 'full_name' => 'Alleh Segun', 'title' => 'Mr', 'display_order' => 220],
            ['public_id' => '40e1a03d-90c7-43d7-a959-542c63d80535', 'full_name' => 'Fatola', 'title' => 'Mr', 'display_order' => 230],
            ['public_id' => '3931dd8f-2843-4755-83e3-6593d05c5950', 'full_name' => 'Adeboye', 'title' => 'Mr', 'display_order' => 240],
            ['public_id' => 'ae4f129e-3410-4827-b982-3e8137c3136c', 'full_name' => 'Dahood', 'title' => 'Mr', 'display_order' => 250],
            ['public_id' => 'b42ad830-e086-45b8-8f2d-653e95938e7e', 'full_name' => 'Ishola Kolawole', 'title' => 'Mr', 'display_order' => 260],
            ['public_id' => '416ca635-fc43-4e45-82fd-ca471b4ded62', 'full_name' => 'Oduntan Oluwatoyin', 'title' => 'Mr', 'display_order' => 270],
            ['public_id' => 'fe062897-3dd2-4678-b115-732a027e5ae6', 'full_name' => 'Abidoye', 'title' => 'Mr', 'display_order' => 280],
        ];

        foreach ($people as $person) {
            $personStatement->execute($person);
        }

        $positionStatement = $database->prepare(<<<'SQL'
            INSERT INTO leadership_positions (leadership_group_id, name, display_order, active)
            SELECT id, :position_name, 10, 1
            FROM leadership_groups
            WHERE slug = :group_slug
            ON DUPLICATE KEY UPDATE
                display_order = VALUES(display_order),
                active = 1
            SQL);

        $positions = [
            'advisory-board' => 'Advisory Board Member',
            'governing-board' => 'Governing Board Member',
            'management-team' => 'Management Team Member',
            'programme-coordinators' => 'Programme Coordinator',
        ];
        foreach ($positions as $groupSlug => $positionName) {
            $positionStatement->execute(['position_name' => $positionName, 'group_slug' => $groupSlug]);
        }

        $assignmentStatement = $database->prepare(<<<'SQL'
            INSERT INTO leadership_assignments (person_id, leadership_position_id, display_order, active)
            SELECT people.id, positions.id, :display_order, 1
            FROM people
            INNER JOIN leadership_groups AS leadership_group ON leadership_group.slug = :group_slug
            INNER JOIN leadership_positions AS positions
                ON positions.leadership_group_id = leadership_group.id
               AND positions.name = :position_name
            WHERE people.public_id = :public_id
            ON DUPLICATE KEY UPDATE
                display_order = VALUES(display_order),
                active = 1
            SQL);

        $assignments = [
            ['group' => 'advisory-board', 'position' => 'Advisory Board Member', 'people' => [
                '2df7567e-3d2a-4fb9-9023-ec0a7f4cb3bb',
                '03adff46-f795-4fe4-9840-afa4515c4474',
            ]],
            ['group' => 'governing-board', 'position' => 'Governing Board Member', 'people' => [
                '92aca025-9503-459c-8a0f-c47473769609',
                '184910c2-397c-4c58-9356-30dfb581b0d8',
                'eef471e3-6b7b-42a5-88bd-5fc88befb46a',
                'f781a35d-ada2-4bf6-bca6-39ddd5e03730',
                '5430c743-a42a-4132-9006-e0afa8bcba81',
                '9e028a37-6b21-4435-979a-b039c716fba9',
                'bc2a7663-bcd0-4afa-be76-2aaf3ac91816',
                '091e5720-b4cb-43bf-90d4-f53fbeedbee2',
                '58779074-0e2f-4768-ab38-38688312f440',
                '6541dd47-ec81-407b-b439-cdddd940ee91',
            ]],
            ['group' => 'management-team', 'position' => 'Management Team Member', 'people' => [
                'f9a441f3-9754-4383-a28a-ebf5745a41e3',
                '725dff8a-96da-4e1d-92cf-bbbc2d24eb7f',
                'bc2a7663-bcd0-4afa-be76-2aaf3ac91816',
                '019d8d8e-12c8-4678-b155-0f6167f08df9',
                '285f52fb-88ca-4ee1-8db2-383a02ed6a57',
                '3b2197e3-a476-4be3-b0d5-005c09bc51d8',
                '848dd552-cf99-424c-aa9e-9c60f7a50755',
                'd2d0082b-e224-4c11-91df-6282e107caeb',
                'fb0968af-78e3-45ba-8981-170a59fc367f',
                'e686a23f-1f6b-4220-ac3e-56e54588b16e',
                '322e815e-246d-4230-b562-890e7e516ffd',
                '40e1a03d-90c7-43d7-a959-542c63d80535',
            ]],
            ['group' => 'programme-coordinators', 'position' => 'Programme Coordinator', 'people' => [
                '3931dd8f-2843-4755-83e3-6593d05c5950',
                'ae4f129e-3410-4827-b982-3e8137c3136c',
                'b42ad830-e086-45b8-8f2d-653e95938e7e',
                '416ca635-fc43-4e45-82fd-ca471b4ded62',
                'fe062897-3dd2-4678-b115-732a027e5ae6',
            ]],
        ];

        foreach ($assignments as $group) {
            foreach ($group['people'] as $index => $publicId) {
                $assignmentStatement->execute([
                    'display_order' => ($index + 1) * 10,
                    'group_slug' => $group['group'],
                    'position_name' => $group['position'],
                    'public_id' => $publicId,
                ]);
            }
        }
    }
};
