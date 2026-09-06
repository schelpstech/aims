<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Connection;
use PDO;

abstract class Repository
{
    public function __construct(protected readonly Connection $database)
    {
    }

    protected function connection(?string $name = null): PDO
    {
        return $this->database->get($name);
    }
}

