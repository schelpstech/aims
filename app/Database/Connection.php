<?php

declare(strict_types=1);

namespace App\Database;

use App\Config\Config;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class Connection
{
    /** @var array<string, PDO> */
    private array $connections = [];

    public function __construct(private readonly Config $config)
    {
    }

    public function get(?string $name = null): PDO
    {
        $name ??= (string) $this->config->get('database.default', 'mysql');

        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        $settings = $this->config->get('database.connections.' . $name);
        if (!is_array($settings)) {
            throw new InvalidArgumentException(sprintf('Database connection %s is not configured.', $name));
        }

        if (($settings['driver'] ?? null) !== 'mysql') {
            throw new InvalidArgumentException('Only the configured MySQL connection is supported.');
        }

        $database = trim((string) ($settings['database'] ?? ''));
        $username = trim((string) ($settings['username'] ?? ''));
        if ($database === '' || $username === '') {
            throw new RuntimeException('Database configuration is incomplete.');
        }

        $charset = (string) ($settings['charset'] ?? 'utf8mb4');
        if (preg_match('/^[A-Za-z0-9_]+$/', $charset) !== 1) {
            throw new RuntimeException('Database charset configuration is invalid.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) ($settings['host'] ?? '127.0.0.1'),
            (int) ($settings['port'] ?? 3306),
            $database,
            $charset,
        );

        $this->connections[$name] = new PDO(
            $dsn,
            $username,
            (string) ($settings['password'] ?? ''),
            (array) ($settings['options'] ?? []),
        );

        return $this->connections[$name];
    }

    /** @template T
     *  @param callable(PDO): T $callback
     *  @return T
     */
    public function transaction(callable $callback, ?string $connection = null): mixed
    {
        $pdo = $this->get($connection);
        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}

