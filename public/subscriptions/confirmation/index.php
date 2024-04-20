<?php

declare(strict_types=1);

namespace SimpleNewsletter;

(function (): never {
    try {
        $email = $_GET['email'] ?? null;
        $feedUri = $_GET['uri'] ?? null;
        $token = $_GET['token'] ?? null;

        if (! ($email && $feedUri && $token)) {
            \header('HTTP/1.0 400 Bad Request', true, 400);
            echo 'Fields "email", "uri" and "token" are required';
            exit;
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
