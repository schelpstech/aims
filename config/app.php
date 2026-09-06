<?php

declare(strict_types=1);

return [
    'name' => $environment->get('APP_NAME', 'AIMS Nigeria'),
    'environment' => $environment->get('APP_ENV', 'production'),
    'debug' => $environment->bool('APP_DEBUG', false),
    'url' => $environment->get('APP_URL', ''),
    'timezone' => $environment->get('APP_TIMEZONE', 'Africa/Lagos'),
];

