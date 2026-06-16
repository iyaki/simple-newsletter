<?php

declare(strict_types=1);

return (
    /** @return array<string, string> */
    static function (): array {
        $dbPath = \getenv('NEWSLETTER_DB_PATH');
        return [
            'dsn' => 'sqlite:' . (\is_string($dbPath) ? $dbPath : ':memory:'),
        ];
    }
)();
