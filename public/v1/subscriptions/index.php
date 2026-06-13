<?php

declare(strict_types=1);

namespace SimpleNewsletter;

use SimpleNewsletter\Components\EndUserException;

// mago-ignore
(function (): never {
    $c = new Container();
    $responder = $c->responder();

    header('X-Robots-Tag: noindex, nofollow');

    try {
        $return = $_GET['return'] ?? null;
        $redirect = $_GET['redirect'] ?? null;
        $redirect = $redirect === 'false' ? false : (bool) $redirect;
        $acceptHeader = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');

        if ($return !== null && !\filter_var($return, \FILTER_VALIDATE_URL)) {
            throw new EndUserException('Invalid return URL');
        }
        $scheme = $return !== null ? \parse_url($return, \PHP_URL_SCHEME) : null;
        if ($scheme !== null && !\in_array($scheme, ['http', 'https'], true)) {
            throw new EndUserException('Return URL must use http or https scheme');
        }

        if ($redirect && ! $return) {
            $responseBuilder = $responder->responseBuilderFromContentNegotiation($acceptHeader);
            throw new EndUserException('"return" must be set when using "redirect"');
        }

        $c->rateLimiter()->check($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 'subscribe');

        $responseBuilder = (
            $redirect
            ? $responder->responseBuilderFromRedirect()
            : $responder->responseBuilderFromContentNegotiation($acceptHeader)
        );

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
