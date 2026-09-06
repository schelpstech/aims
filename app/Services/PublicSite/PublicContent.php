<?php

declare(strict_types=1);

namespace App\Services\PublicSite;

use App\Config\Config;
use RuntimeException;

final class PublicContent
{
    public function __construct(private readonly Config $config)
    {
    }

    /** @return array<string, mixed> */
    public function site(): array
    {
        return (array) $this->config->get('public.site', []);
    }

    /** @return list<array{label: string, path: string}> */
    public function navigation(): array
    {
        return (array) $this->config->get('public.navigation', []);
    }

    /** @return array<string, string> */
    public function page(string $key): array
    {
        $page = $this->config->get('public.pages.' . $key);
        if (!is_array($page)) {
            throw new RuntimeException(sprintf('Public page configuration %s was not found.', $key));
        }

        return $page;
    }

    /** @return list<string> */
    public function collection(string $key): array
    {
        $items = $this->config->get('public.' . $key, []);

        return is_array($items) ? array_values($items) : [];
    }
}

