<?php

declare(strict_types=1);

$stampPath = __DIR__ . '/.test-db-path';
$stampContent = \file_exists($stampPath) ? \file_get_contents($stampPath) : 'NOT_FOUND';
$logContent = \date('H:i:s') . ' stamp=' . ($stampContent === false ? 'false' : $stampContent) . "\n";
\file_put_contents(__DIR__ . '/.router-debug.log', $logContent, \FILE_APPEND);

if (\file_exists($stampPath) && $stampContent !== false) {
    $dbPath = \trim($stampContent);
    $_ENV['NEWSLETTER_DB_PATH'] = $dbPath;
    $check = $_ENV['NEWSLETTER_DB_PATH'];
}

$_ENV['SECRET_KEY'] = 'test-secret-for-integration';

return false;
