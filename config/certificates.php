<?php

declare(strict_types=1);

return [
    'number_prefix' => $environment->get('CERTIFICATE_NUMBER_PREFIX', 'AIMS-CERT'),
    'signing_key' => $environment->get('CERTIFICATE_SIGNING_KEY', ''),
    'verification_url' => $environment->get('CERTIFICATE_VERIFICATION_URL', ''),
];
