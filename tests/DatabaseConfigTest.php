<?php

declare(strict_types=1);

test('database config falls back to the file DB when NEWSLETTER_DB_PATH is not set', function (): void {
    $previous = \getenv('NEWSLETTER_DB_PATH');
    \putenv('NEWSLETTER_DB_PATH');

    try {
        /** @var array{dsn: string} $config */
        $config = require __DIR__ . '/../config/database.php';
    } finally {
        if ($previous !== false) {
            \putenv('NEWSLETTER_DB_PATH=' . $previous);
        }
    }

    expect($config['dsn'])->toStartWith('sqlite:')
        ->and($config['dsn'])->toContain('data/database.sqlite3')
        ->and($config['dsn'])->not->toContain(':memory:');
});

test('database config honors NEWSLETTER_DB_PATH when set', function (): void {
    $previous = \getenv('NEWSLETTER_DB_PATH');
    \putenv('NEWSLETTER_DB_PATH=/tmp/custom-newsletter.db');

    try {
        /** @var array{dsn: string} $config */
        $config = require __DIR__ . '/../config/database.php';
    } finally {
        if ($previous !== false) {
            \putenv('NEWSLETTER_DB_PATH=' . $previous);
        }
    }

    expect($config['dsn'])->toBe('sqlite:/tmp/custom-newsletter.db');
});
