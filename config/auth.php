<?php

declare(strict_types=1);

return [
    'password_min_length' => $environment->int('AUTH_PASSWORD_MIN_LENGTH', 12),
    'password_max_length' => $environment->int('AUTH_PASSWORD_MAX_LENGTH', 4096),
    'verification_ttl_minutes' => $environment->int('AUTH_VERIFICATION_TTL_MINUTES', 1440),
    'reset_ttl_minutes' => $environment->int('AUTH_RESET_TTL_MINUTES', 60),
    'login' => [
        'identity_attempts' => $environment->int('AUTH_LOGIN_IDENTITY_ATTEMPTS', 5),
        'ip_attempts' => $environment->int('AUTH_LOGIN_IP_ATTEMPTS', 50),
        'decay_minutes' => $environment->int('AUTH_LOGIN_DECAY_MINUTES', 15),
        'lock_minutes' => $environment->int('AUTH_LOGIN_LOCK_MINUTES', 15),
    ],
    'registration' => [
        'identity_attempts' => $environment->int('AUTH_REGISTRATION_IDENTITY_ATTEMPTS', 3),
        'ip_attempts' => $environment->int('AUTH_REGISTRATION_IP_ATTEMPTS', 20),
        'decay_minutes' => $environment->int('AUTH_REGISTRATION_DECAY_MINUTES', 60),
    ],
    'password_reset' => [
        'identity_attempts' => $environment->int('AUTH_RESET_IDENTITY_ATTEMPTS', 3),
        'ip_attempts' => $environment->int('AUTH_RESET_IP_ATTEMPTS', 20),
        'decay_minutes' => $environment->int('AUTH_RESET_DECAY_MINUTES', 60),
    ],
    'password_change' => [
        'identity_attempts' => $environment->int('AUTH_PASSWORD_CHANGE_ATTEMPTS', 5),
        'ip_attempts' => $environment->int('AUTH_PASSWORD_CHANGE_IP_ATTEMPTS', 20),
        'decay_minutes' => $environment->int('AUTH_PASSWORD_CHANGE_DECAY_MINUTES', 15),
    ],
];
