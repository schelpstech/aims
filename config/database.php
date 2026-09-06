<?php

declare(strict_types=1);

return [
    'default' => $environment->get('DB_DRIVER', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => $environment->get('DB_HOST', '127.0.0.1'),
            'port' => $environment->int('DB_PORT', 3306),
            'database' => $environment->get('DB_DATABASE', ''),
            'username' => $environment->get('DB_USERNAME', ''),
            'password' => $environment->get('DB_PASSWORD', ''),
            'charset' => $environment->get('DB_CHARSET', 'utf8mb4'),
            'options' => [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ],
    ],
];
