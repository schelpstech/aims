<?php

declare(strict_types=1);

namespace App\Auth;

final class PasswordPolicy
{
    public function __construct(
        private readonly int $minimumLength = 12,
        private readonly int $maximumLength = 4096,
    ) {
    }

    /** @return list<string> */
    public function errors(string $password): array
    {
        $length = mb_strlen($password);
        $errors = [];

        if ($length < $this->minimumLength) {
            $errors[] = sprintf('Use at least %d characters.', $this->minimumLength);
        }
        if ($length > $this->maximumLength) {
            $errors[] = sprintf('Use no more than %d characters.', $this->maximumLength);
        }

        return $errors;
    }

    public function maximumLength(): int
    {
        return $this->maximumLength;
    }

    public function minimumLength(): int
    {
        return $this->minimumLength;
    }
}
