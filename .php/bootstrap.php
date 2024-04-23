<?php

declare(strict_types=1);

if (\file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';

    \Sentry\init([
        'dsn' => 'https://c0548d393c74db8777af292c0a4cfcde@o249980.ingest.us.sentry.io/4507137129578496',
    ]);
} else {
    \trigger_error('composer autoload file not found. Please run `composer install`');
}
