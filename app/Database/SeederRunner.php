<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;
use Throwable;

final class SeederRunner
{
    public function __construct(
        private readonly PDO $database,
        private readonly string $path,
    ) {
    }

    /** @return list<string> */
    public function run(): array
    {
        $files = glob(rtrim($this->path, '/\\') . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files, SORT_STRING);
        $completed = [];

        foreach ($files as $file) {
            $seeder = require $file;
            if (!$seeder instanceof Seeder) {
                throw new RuntimeException(sprintf('Seeder %s must return a Seeder instance.', basename($file)));
            }

            $this->database->beginTransaction();
            try {
                $seeder->run($this->database);
                $this->database->commit();
                $completed[] = pathinfo($file, PATHINFO_FILENAME);
            } catch (Throwable $exception) {
                if ($this->database->inTransaction()) {
                    $this->database->rollBack();
                }
                throw $exception;
            }
        }

        return $completed;
    }
}

