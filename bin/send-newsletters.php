<?php

declare(strict_types=1);

namespace SimpleNewsletter;

(static function (): never {
    try {
        $c = new Container();

        $datetime = new \DateTimeImmutable();

        $c->subscriptions()->sendScheduled($datetime);

        echo $datetime->format('Y-m-d H:i:s') . PHP_EOL;
    } catch (\Throwable $throwable) {
        error_log('Newsletter delivery failed: ' . $throwable->getMessage());
        // Don't exit with error code - partial success is OK
    }

    exit;
})();
