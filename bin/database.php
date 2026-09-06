<?php

declare(strict_types=1);

use App\Config\Config;
use App\Config\Environment;
use App\Database\Connection;
use App\Database\Migrator;
use App\Database\SeederRunner;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$basePath = dirname(__DIR__);
$autoload = $basePath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Application dependencies are not installed. Run composer install.\n");
    exit(1);
}

require $autoload;

$command = $argv[1] ?? 'status';
$mutatingCommands = ['migrate', 'rollback', 'seed'];

try {
    $environment = Environment::load($basePath . DIRECTORY_SEPARATOR . '.env');
    $config = Config::load($basePath . DIRECTORY_SEPARATOR . 'config', $environment);
    $applicationEnvironment = (string) $config->get('app.environment', 'production');
    $force = in_array('--force', $argv, true);

    if (in_array($command, $mutatingCommands, true) && $applicationEnvironment === 'production' && !$force) {
        throw new RuntimeException('Refusing to modify a production database without --force.');
    }

    $database = (new Connection($config))->get();
    $migrator = new Migrator($database, $basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations');

    switch ($command) {
        case 'migrate':
            $completed = $migrator->migrate();
            foreach ($completed as $migration) {
                fwrite(STDOUT, "Migrated: {$migration}\n");
            }
            if ($completed === []) {
                fwrite(STDOUT, "No pending migrations.\n");
            }
            break;

        case 'rollback':
            $batches = 1;
            foreach ($argv as $argument) {
                if (preg_match('/^--batches=([0-9]+)$/', $argument, $matches) === 1) {
                    $batches = max(1, (int) $matches[1]);
                }
            }
            $completed = $migrator->rollback($batches);
            foreach ($completed as $migration) {
                fwrite(STDOUT, "Rolled back: {$migration}\n");
            }
            if ($completed === []) {
                fwrite(STDOUT, "Nothing to roll back.\n");
            }
            break;

        case 'seed':
            $seeders = new SeederRunner($database, $basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'seeders');
            foreach ($seeders->run() as $seeder) {
                fwrite(STDOUT, "Seeded: {$seeder}\n");
            }
            break;

        case 'status':
            foreach ($migrator->status() as $migration) {
                fwrite(STDOUT, sprintf(
                    "%-60s %-8s %s\n",
                    $migration['migration'],
                    $migration['status'],
                    $migration['batch'] === null ? '-' : 'batch ' . $migration['batch'],
                ));
            }
            break;

        default:
            throw new RuntimeException('Unknown command. Use status, migrate, rollback, or seed.');
    }
} catch (Throwable $exception) {
    fwrite(STDERR, sprintf("Database command failed: %s\n", $exception->getMessage()));
    exit(1);
}

