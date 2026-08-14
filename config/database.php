<?php

declare(strict_types=1);

return (
    /** @return array<string, string> */
    static function (): array {
        $dbPath = \getenv('NEWSLETTER_DB_PATH');
        if ($dbPath === false || $dbPath === '') {
            $dbPath = __DIR__ . '/../data/database.sqlite3';
        }
        return [
            'dsn' => 'sqlite:' . $dbPath,
        ];
    }
)();
