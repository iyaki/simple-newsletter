<?php

declare(strict_types=1);

return (static function() {
    $dbPath = __DIR__ . '/../data/database.sqlite3';
    return [
        'dsn' => 'sqlite:' . $dbPath,
    ];
})();
