<?php

declare(strict_types=1);

namespace SimpleNewsletter;

use SimpleNewsletter\Components\EndUserException;

(function (): never {
    $c = new Container();
    $responder = $c->responder();
    $responseBuilder = $responder->responseBuilderFromContentNegotiation($_SERVER['HTTP_ACCEPT']);

    try {

        $email = $_GET['email'] ?? null;
        $feedUri = $_GET['uri'] ?? null;

        if (! ($email && $feedUri)) {
            throw new EndUserException('Fields "email" and "uri" are required');
        }

        $c->subscriptions()->add($feedUri, $email);

        $responder->sendResponse($responseBuilder->fromString(
            "An email confirmation has been sent to {$email}.",
            'Please check your inbox (and your spam folder).'
        ));
    } catch (EndUserException $e) {
        $responder->sendResponse($responseBuilder->fromEndUserException($e));
    }

    exit;
})();
