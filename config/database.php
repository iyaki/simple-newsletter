<?php

declare(strict_types=1);

return (static function (): array {
    $dbPath = \getenv('NEWSLETTER_DB_PATH') ?: __DIR__ . '/../data/database.sqlite3';
    return [
        'dsn' => 'sqlite:' . $dbPath,
    ];
})();
