<?php

declare(strict_types=1);

use function Sentry\init;

(static function (): void {
    if (! \file_exists(__DIR__ . '/../vendor/autoload.php')) {
        $scriptFilename = $_SERVER['SCRIPT_FILENAME'];
        if (\basename( $scriptFilename, suffix: '.php') !== 'composer') {
            \trigger_error('composer autoload file not found. Please run `composer install`');
        }

        return;
    }

    require __DIR__ . '/../vendor/autoload.php';

    $SENTRY_DSN = \getenv('SENTRY_DSN');
    if (function_exists('\Sentry\init') && $SENTRY_DSN) {
        init([
            'dsn' => $SENTRY_DSN,
        ]);
    }
})();
