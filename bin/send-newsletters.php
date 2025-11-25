<?php

declare(strict_types=1);

namespace SimpleNewsletter;

(function (): never {
    $c = new Container();

    $datetime = new \DateTimeImmutable();

    $c->subscriptions()->sendScheduled($datetime);

    echo $datetime->format('Y-m-d H:i:s') . "\n";

    exit;
})();
