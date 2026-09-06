<?php

declare(strict_types=1);

$production = $environment->get('APP_ENV', 'production') === 'production';

return [
    'name' => $environment->get('SESSION_NAME', 'aims_session'),
    'lifetime' => $environment->int('SESSION_LIFETIME', 120),
    'save_path' => $environment->get('SESSION_SAVE_PATH', 'storage/sessions'),
    'path' => '/',
    'domain' => $environment->get('SESSION_DOMAIN', ''),
    'secure' => $environment->bool('SESSION_SECURE_COOKIE', $production),
    'http_only' => true,
    'same_site' => $environment->get('SESSION_SAME_SITE', 'Lax'),
];
