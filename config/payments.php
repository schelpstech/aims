<?php

declare(strict_types=1);

return [
    // No provider is enabled until an approved adapter and server-side credentials are configured.
    'gateway' => strtolower($environment->get('PAYMENT_GATEWAY', 'disabled')),
    'callback_url' => $environment->get('PAYMENT_CALLBACK_URL', ''),
];
