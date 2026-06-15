<?php

declare(strict_types=1);

namespace Tests\E2e;

/**
 * Database cleaner for e2e tests
 */
final readonly class DatabaseCleaner
{
    /**
     * Clean database between tests
     *
     * @throws \PDOException
     */
    public static function cleanDatabase(\PDO $db): void
    {
        $db->exec('DELETE FROM subscriptions');
        $db->exec('DELETE FROM feeds');
        $db->exec('DELETE FROM rate_limit');
    }
}
