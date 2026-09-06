<?php

declare(strict_types=1);

return [
    'number_prefix' => $environment->get('MEMBERSHIP_NUMBER_PREFIX', 'AIMS'),
    'documents' => [
        'path' => $environment->get('MEMBERSHIP_DOCUMENT_PATH', 'storage/private/membership-documents'),
        'max_bytes' => $environment->int('MEMBERSHIP_DOCUMENT_MAX_BYTES', 5 * 1024 * 1024),
        'allowed_mime_types' => ['application/pdf', 'image/jpeg', 'image/png'],
    ],
];
