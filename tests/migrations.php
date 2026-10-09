<?php

declare(strict_types=1);

/** Apply every migration in sorted order to an existing PDO handle. */
function apply_migrations(\PDO $pdo): void
{
    $files = \glob(__DIR__ . '/../migrations/*.sql');
    if ($files !== false) {
        \sort($files);
        foreach ($files as $file) {
            $sql = \file_get_contents($file);
            if ($sql !== false) {
                $pdo->exec($sql);
            }
        }
    }
}

/** Recreate the SQLite database at $path (plus stale -wal/-shm sidecars) from migrations. */
function rebuild_sqlite_db(string $path): \PDO
{
    foreach ([$path, $path . '-wal', $path . '-shm'] as $file) {
        if (\file_exists($file)) {
            \unlink($file);
        }
    }
    $pdo = new \PDO('sqlite:' . $path);
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    apply_migrations($pdo);

    return $pdo;
}
