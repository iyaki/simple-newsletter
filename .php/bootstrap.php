<?php

declare(strict_types=1);

if (\file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} else {
    \trigger_error('composer autoload file not found. Please run `composer install`');
}

// TODO: Add Sentry
