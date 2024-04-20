<?php

declare(strict_types=1);

namespace SimpleNewsletter;

use PHPMailer\PHPMailer\PHPMailer;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Models\Sender;

(function (): never {
    try {
        $email = $_GET['email'] ?? null;
        $feedUri = $_GET['uri'] ?? null;

        if (! ($email && $feedUri)) {
            \header('HTTP/1.0 400 Bad Request', true, 400);
            echo 'Fields "email" and "uri" are required';
            exit;
        }

        $c = new Container();

        $c->subscriptions()->add($feedUri, $email);

        echo "An email confirmation has been sent to {$email}. Please check your inbox (and your spam folder).";
    } catch (EndUserException $e) {
        \header('HTTP/1.0 400 Bad Request', true, 400);
        echo $e->getMessage();
    }
    exit;
})();
