<?php

declare(strict_types=1);

$stampPath = __DIR__ . '/.test-db-path';
$stampContent = \file_exists($stampPath) ? \file_get_contents($stampPath) : 'NOT_FOUND';
\file_put_contents(__DIR__ . '/.router-debug.log', \date('H:i:s') . ' stamp=' . \var_export($stampContent, true) . "\n", \FILE_APPEND);

if (\file_exists($stampPath) && $stampContent !== false) {
    $dbPath = \trim($stampContent);
    \putenv('NEWSLETTER_DB_PATH=' . $dbPath);
    // Verify it worked
    $check = \getenv('NEWSLETTER_DB_PATH');
    \file_put_contents(__DIR__ . '/.router-debug.log', \date('H:i:s') . " putenv called, getenv after=$check\n", \FILE_APPEND);
}

\putenv('SECRET_KEY=test-secret-for-integration');

return false;
