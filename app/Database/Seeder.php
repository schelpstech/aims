<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

interface Seeder
{
    public function run(PDO $database): void;
}

