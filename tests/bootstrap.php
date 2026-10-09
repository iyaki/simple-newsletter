<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

\DG\BypassFinals::enable();

// The app fails closed without a non-empty SECRET_KEY (audit fix); make sure
// the unit suite always runs against a configured container.
if (\getenv('SECRET_KEY') === false || \getenv('SECRET_KEY') === '') {
    \putenv('SECRET_KEY=unit-test-secret-do-not-use-in-production');
}
