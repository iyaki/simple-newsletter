<?php

declare(strict_types=1);

namespace SimpleNewsletter;

(static function (): never {
    // ponytail: one delivery run at a time; a run outliving the crontab hour
    // must not duplicate the whole batch against the same watermark snapshot.
    $lockFile = \fopen(__DIR__ . '/../data/send-newsletters.lock', mode: 'c');
    if ($lockFile === false) {
        // Lock storage unavailable: proceed unlocked (duplicate risk) rather
        // than silently skipping delivery.
        error_log('send-newsletters: could not open data/send-newsletters.lock; running without mutual exclusion.');
    } elseif (! \flock($lockFile, \LOCK_EX | \LOCK_NB)) {
        exit(0); // previous run still in progress
    }

    try {
        $c = new Container();

        $datetime = new \DateTimeImmutable();

        $c->delivery()->sendScheduled($datetime);

        echo $datetime->format('Y-m-d H:i:s') . PHP_EOL;
    } catch (\Throwable $throwable) {
        error_log('Newsletter delivery failed: ' . $throwable->getMessage());

        // Don't exit with error code - partial success is OK
    }

    exit();
})();
