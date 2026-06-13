<?php
declare(strict_types=1);

namespace Tests\E2e;

trait DatabaseCleaner
{
    /**
     * Clean all data from test database tables
     */
    public function cleanDatabase(): void
    {
        $dbPath = \getenv('NEWSLETTER_DB_PATH');
        if ($dbPath && \file_exists($dbPath)) {
            $pdo = new \PDO("sqlite:{$dbPath}");
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            // Delete all data but keep schema
            $pdo->exec('DELETE FROM subscriptions');
            $pdo->exec('DELETE FROM feeds');
        }
    }
}