<?php

declare(strict_types=1);

namespace SimpleNewsletter;

use SimpleNewsletter\Components\ErrorReporter;

(static function (): never {
    // ponytail: one delivery run at a time; a run outliving the crontab hour
    // must not duplicate the whole batch against the same watermark snapshot.
    $lockFile = \fopen(__DIR__ . '/../data/send-newsletters.lock', mode: 'c');
    if ($lockFile === false) {
        // Lock storage unavailable: proceed unlocked (duplicate risk) rather
        // than silently skipping delivery.
        ErrorReporter::report('send-newsletters: could not open data/send-newsletters.lock; running without mutual exclusion.');
    } elseif (! \flock($lockFile, \LOCK_EX | \LOCK_NB)) {
        exit(0); // previous run still in progress
    }

    try {
        $c = new Container();

        $datetime = new \DateTimeImmutable();

        $c->delivery()->sendScheduled($datetime);

        echo $datetime->format('Y-m-d H:i:s') . PHP_EOL;
    } catch (\RuntimeException $configurationException) {
        // Fail closed and loud: a misconfigured deployment (e.g. missing
        // SECRET_KEY) must fail the cron run, not exit as if it succeeded.
        ErrorReporter::report('Configuration error: ' . $configurationException->getMessage(), $configurationException);
        exit(1);
    } catch (\Throwable $throwable) {
        ErrorReporter::report('Newsletter delivery failed: ' . $throwable->getMessage(), $throwable);

        // Per-feed failures are already quarantined inside NewsletterDelivery,
        // so anything reaching here aborted the whole run: fail the cron.
        exit(1);
    }

    exit(0);
})();
