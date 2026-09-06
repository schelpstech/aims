<?php

declare(strict_types=1);

use App\Database\Seeder;

return new class implements Seeder {
    public function run(\PDO $database): void
    {
        $statement = $database->prepare(<<<'SQL'
            INSERT INTO system_settings (
                setting_group,
                setting_key,
                setting_value,
                value_type,
                is_sensitive,
                is_public,
                autoload,
                description
            ) VALUES (
                :setting_group,
                :setting_key,
                :setting_value,
                :value_type,
                0,
                :is_public,
                1,
                :description
            )
            ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key)
            SQL);

        $settings = [
            [
                'setting_group' => 'organisation',
                'setting_key' => 'organisation_name',
                'setting_value' => 'Association for Information and Management Sciences, Nigeria',
                'value_type' => 'string',
                'is_public' => 1,
                'description' => 'Official organisation name verified by the master project charter.',
            ],
            [
                'setting_group' => 'organisation',
                'setting_key' => 'short_name',
                'setting_value' => 'AIMS Nigeria',
                'value_type' => 'string',
                'is_public' => 1,
                'description' => 'Organisation short name verified by the master project charter.',
            ],
        ];

        foreach ($settings as $setting) {
            $statement->execute($setting);
        }
    }
};

