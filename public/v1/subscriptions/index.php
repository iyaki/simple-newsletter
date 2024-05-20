<?php

declare(strict_types=1);

namespace SimpleNewsletter;

use SimpleNewsletter\Components\EndUserException;

(function (): never {
    $c = new Container();
    $responder = $c->responder();

    try {
        $return = $_GET['return'] ?? null;
        $redirect = $_GET['redirect'] ?? null;
        $redirect = $redirect === 'false' ? false : (bool) $redirect;

        if ($redirect && ! $return) {
            $responseBuilder = $responder->responseBuilderFromContentNegotiation($_SERVER['HTTP_ACCEPT']);
            throw new EndUserException('"return" must be set when using "redirect"');
        }

        $responseBuilder = (
            $redirect
            ? $responder->responseBuilderFromRedirect()
            : $responder->responseBuilderFromContentNegotiation($_SERVER['HTTP_ACCEPT'])
        );

        if ($redirect && ! $return) {
            throw new EndUserException('"return" must be set when using "redirect"');
        }

        $email = $_GET['email'] ?? null;
        $feedUri = $_GET['uri'] ?? null;

        if (! ($email && $feedUri)) {
            throw new EndUserException('Fields "email" and "uri" are required');
        }

        $c->subscriptions()->add($feedUri, $email);

        $responder->sendResponse($responseBuilder->fromString(
            "An email confirmation has been sent to {$email}.",
            'Please check your inbox (and your spam folder).',
            $return
        ));
    } catch (EndUserException $e) {
        $responder->sendResponse($responseBuilder->fromEndUserException(
            $e,
            $return
        ));
    }

    exit;
})();
