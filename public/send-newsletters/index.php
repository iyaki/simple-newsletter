<?php

declare(strict_types=1);

namespace SimpleNewsletter;

use SimpleNewsletter\Components\EndUserException;

(function (): never {
    $c = new Container();

    $datetime = new \DateTimeImmutable();

    $c->subscriptions()->sendScheduled($datetime);

    header('X-Robots-Tag: noindex, nofollow');

    echo $datetime->format('Y-m-d H:i:s') . "\n";

    exit;

})();
