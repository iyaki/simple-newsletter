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
        $token = $_GET['token'] ?? null;

        if (! ($email && $feedUri && $token)) {
            throw new EndUserException('Fields "email", "uri" and "token" are required');
        }


        $c->subscriptions()->cancel($feedUri, $email, $token);

        $responder->sendResponse($responseBuilder->fromString(
            'Subscription successfully cancelled.',
            ''
        ));
    } catch (EndUserException $e) {
        $responder->sendResponse($responseBuilder->fromEndUserException($e));
    }
    exit;
})();
