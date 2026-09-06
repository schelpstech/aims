<?php

declare(strict_types=1);

return [
    'level' => $environment->get('LOG_LEVEL', 'info'),
    'path' => $environment->get('LOG_PATH', 'storage/logs/application.log'),
];

