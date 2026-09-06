<?php

declare(strict_types=1);

return [
    'headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        'Content-Security-Policy' => "default-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'; object-src 'none'",
    ],
    // HSTS is emitted only when PHP can confirm that the current request is HTTPS.
    'https_headers' => [
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
    ],
];
