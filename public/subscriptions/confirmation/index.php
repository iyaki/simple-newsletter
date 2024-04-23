<?php

declare(strict_types=1);

namespace SimpleNewsletter;

use SimpleNewsletter\Components\EndUserException;

(function (): never {
    try {
        $email = $_GET['email'] ?? null;
        $feedUri = $_GET['uri'] ?? null;
        $token = $_GET['token'] ?? null;

        if (! ($email && $feedUri && $token)) {
            throw new EndUserException('Fields "email", "uri" and "token" are required');
        }

        $c = new Container();

        $c->subscriptions()->confirm($feedUri, $email, $token);

        echo 'Subscription confirmed.';
    } catch (EndUserException $e) {
        \header('HTTP/1.0 400 Bad Request', true, 400);
        echo $e->getMessage();
    }
    exit;
})();
