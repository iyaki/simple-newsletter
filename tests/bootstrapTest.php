<?php

declare(strict_types=1);

const BOOTSTRAP_PATH = __DIR__ . '/../libs/bootstrap.php';

test('bootstrap loads without error and handles Sentry init', function (): void {
    $_ENV['SENTRY_DSN'] = 'https://examplePublicKey@o0.ingest.sentry.io/0';
    include BOOTSTRAP_PATH;
    unset($_ENV['SENTRY_DSN']);
    expect(true)->toBeTrue();
});
test('bootstrap handles missing autoload file gracefully', function (): void {
    $bakPath = __DIR__ . '/../vendor/autoload.php.bak';
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';

    // Temporarily remove autoload so bootstrap triggers the missing file path
    \rename($autoloadPath, $bakPath);

    // Capture the trigger_error notice
    $errorMsg = '';
    \set_error_handler(function (int $_severity, string $message) use (&$errorMsg): bool {
        $errorMsg = $message;
        return true;
    });

    try {
        include BOOTSTRAP_PATH;
    } finally {
        \restore_error_handler();
        // Restore autoload
        \rename($bakPath, $autoloadPath);
    }

    expect($errorMsg)->toContain('autoload');
});

test('config returns valid DSN array', function (): void {
    /** @var array{dsn: string} $config */
    $config = require __DIR__ . '/../config/database.php';
    expect($config)->toHaveKey('dsn');
    expect($config['dsn'])->toContain('sqlite:');
});
