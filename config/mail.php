<?php

declare(strict_types=1);

return [
    'enabled' => $environment->bool('MAIL_ENABLED', false),
    'from_address' => $environment->get('MAIL_FROM_ADDRESS', ''),
    'from_name' => $environment->get('MAIL_FROM_NAME', 'AIMS Nigeria'),
];
