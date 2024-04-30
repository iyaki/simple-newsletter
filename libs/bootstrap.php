<?php

declare(strict_types=1);

(function () {
    if (! \file_exists(__DIR__ . '/../vendor/autoload.php')) {
        \trigger_error('composer autoload file not found. Please run `composer install`');

        return;
    }

    require __DIR__ . '/../vendor/autoload.php';

    // TODO: Pasar Sentry DSN a variable de entorno, definir variable ENVIRONMENT para diferenciar dev/prod & agregar configuraciones adicionales sentry (user?, version, release)
    // TODO: Re-generar sentry DSN

    $SENTRY_DSN = \getenv('SENTRY_DSN');
    if (function_exists('\Sentry\init') && $SENTRY_DSN) {
        \Sentry\init([
            'dsn' => $SENTRY_DSN,
        ]);
    }
})();
