<?php

declare(strict_types=1);

(function () {
    if (! \file_exists(__DIR__ . '/../vendor/autoload.php')) {
        \trigger_error('composer autoload file not found. Please run `composer install`');

        return;
    }

    require __DIR__ . '/../vendor/autoload.php';

    $SENTRY_DSN = \getenv('SENTRY_DSN');
    if (function_exists('\Sentry\init') && $SENTRY_DSN) {
        \Sentry\init([
            'dsn' => $SENTRY_DSN,
        ]);
    }
})();
