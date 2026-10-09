<?php

declare(strict_types=1);

require __DIR__ . '/../tests/migrations.php';

if (! isset($argv[1])) {
    fwrite(STDERR, "usage: php scripts/e2e-db-init.php <db-path>\n");
    exit(1);
}
rebuild_sqlite_db($argv[1]);
