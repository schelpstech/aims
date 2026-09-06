<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;
use Throwable;

final class Migrator
{
    private const LOCK_NAME = 'aims_database_migrations';

    public function __construct(
        private readonly PDO $database,
        private readonly string $path,
    ) {
    }

    /** @return list<string> */
    public function migrate(): array
    {
        return $this->withLock(function (): array {
            $this->ensureRepository();
            $applied = $this->appliedNames();
            $batch = (int) $this->database->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations')->fetchColumn();
            $completed = [];

            foreach ($this->files() as $name => $file) {
                if (isset($applied[$name])) {
                    continue;
                }

                $this->load($file)->up($this->database);
                $statement = $this->database->prepare(
                    'INSERT INTO migrations (migration, batch, applied_at) VALUES (:migration, :batch, CURRENT_TIMESTAMP(6))',
                );
                $statement->execute(['migration' => $name, 'batch' => $batch]);
                $completed[] = $name;
            }

            return $completed;
        });
    }

    /** @return list<string> */
    public function rollback(int $batches = 1): array
    {
        if ($batches < 1) {
            throw new RuntimeException('Rollback batches must be at least one.');
        }

        return $this->withLock(function () use ($batches): array {
            $this->ensureRepository();
            $batchNumbers = $this->database
                ->query('SELECT DISTINCT batch FROM migrations ORDER BY batch DESC LIMIT ' . (int) $batches)
                ->fetchAll(PDO::FETCH_COLUMN);
            $rolledBack = [];
            $files = $this->files();

            foreach ($batchNumbers as $batch) {
                $statement = $this->database->prepare(
                    'SELECT migration FROM migrations WHERE batch = :batch ORDER BY id DESC',
                );
                $statement->execute(['batch' => $batch]);

                foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $name) {
                    if (!isset($files[$name])) {
                        throw new RuntimeException(sprintf('Migration file %s is missing; rollback stopped.', $name));
                    }

                    $this->load($files[$name])->down($this->database);
                    $delete = $this->database->prepare('DELETE FROM migrations WHERE migration = :migration');
                    $delete->execute(['migration' => $name]);
                    $rolledBack[] = $name;
                }
            }

            return $rolledBack;
        });
    }

    /** @return list<array{migration: string, status: string, batch: int|null, applied_at: string|null}> */
    public function status(): array
    {
        $applied = [];

        if ($this->repositoryExists()) {
            foreach ($this->database->query('SELECT migration, batch, applied_at FROM migrations')->fetchAll() as $row) {
                $applied[$row['migration']] = $row;
            }
        }

        $status = [];
        foreach ($this->files() as $name => $file) {
            $status[] = [
                'migration' => $name,
                'status' => isset($applied[$name]) ? 'applied' : 'pending',
                'batch' => isset($applied[$name]) ? (int) $applied[$name]['batch'] : null,
                'applied_at' => $applied[$name]['applied_at'] ?? null,
            ];
        }

        return $status;
    }

    private function repositoryExists(): bool
    {
        $statement = $this->database->prepare(<<<'SQL'
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'migrations'
            SQL);
        $statement->execute();

        return (int) $statement->fetchColumn() === 1;
    }

    private function ensureRepository(): void
    {
        $this->database->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS migrations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                migration VARCHAR(191) NOT NULL,
                batch INT UNSIGNED NOT NULL,
                applied_at DATETIME(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_migrations_migration (migration),
                KEY idx_migrations_batch (batch)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    /** @return array<string, string> */
    private function files(): array
    {
        $files = glob(rtrim($this->path, '/\\') . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files, SORT_STRING);
        $indexed = [];

        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if (preg_match('/^[0-9]{8}_[0-9]{6}_[a-z0-9_]+$/', $name) !== 1) {
                throw new RuntimeException(sprintf('Invalid migration filename: %s.', basename($file)));
            }
            $indexed[$name] = $file;
        }

        return $indexed;
    }

    /** @return array<string, true> */
    private function appliedNames(): array
    {
        $names = $this->database->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);

        return array_fill_keys($names, true);
    }

    private function load(string $file): Migration
    {
        $migration = require $file;
        if (!$migration instanceof Migration) {
            throw new RuntimeException(sprintf('Migration %s must return a Migration instance.', basename($file)));
        }

        return $migration;
    }

    /** @template T
     *  @param callable(): T $callback
     *  @return T
     */
    private function withLock(callable $callback): mixed
    {
        $statement = $this->database->prepare('SELECT GET_LOCK(:name, 30)');
        $statement->execute(['name' => self::LOCK_NAME]);
        if ((int) $statement->fetchColumn() !== 1) {
            throw new RuntimeException('Could not acquire the database migration lock.');
        }

        try {
            return $callback();
        } catch (Throwable $exception) {
            throw $exception;
        } finally {
            $release = $this->database->prepare('SELECT RELEASE_LOCK(:name)');
            $release->execute(['name' => self::LOCK_NAME]);
        }
    }
}
