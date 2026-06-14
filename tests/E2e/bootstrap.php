<?php

declare(strict_types=1);

// Set test database path
$testDbPath = __DIR__ . '/../../data/test-e2e.db';
$_ENV['NEWSLETTER_DB_PATH'] = $testDbPath;
$_ENV['SECRET_KEY'] = 'test-e2e-secret-key-32chars!';
$_ENV['SERVER_NAME'] = 'http://localhost:8080';

require __DIR__ . '/../../vendor/autoload.php';

/**
 * Initialize test database with fresh schema
 */
if (! function_exists('initTestDatabase')) {
    function init_test_database(string $dbPath): void
    {
        if (\file_exists($dbPath)) {
            \unlink($dbPath);
        }

        $pdo = new \PDO("sqlite:{$dbPath}");
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Apply migrations in order
        $migrationsDir = __DIR__ . '/../../migrations';
        $migrationFiles = [
            '00-setup.sql',
            '01-feeds.sql',
            '02-subscriptions.sql',
            '03-rate-limiting.sql',
            '99-optimizations.sql',
        ];

        foreach ($migrationFiles as $file) {
            $sql = \file_get_contents($migrationsDir . '/' . $file);
            $pdo->exec($sql);
        }
    }
}
