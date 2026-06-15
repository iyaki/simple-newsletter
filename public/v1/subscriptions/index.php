<?php

declare(strict_types=1);

namespace SimpleNewsletter;

use SimpleNewsletter\Components\EndUserException;

$c = new Container();
$responder = $c->responder();

header('X-Robots-Tag: noindex, nofollow');

$return = \is_string($_GET['return'] ?? null) ? $_GET['return'] : null;
$redirect = \is_string($_GET['redirect'] ?? null) ? $_GET['redirect'] : null;
$redirect = $redirect === 'false' ? false : (bool) $redirect;
$acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';

$responseBuilder = $redirect
    ? $responder->responseBuilderFromRedirect()
    : $responder->responseBuilderFromContentNegotiation($acceptHeader);

try {
    if ($return !== null && ! \filter_var($return, \FILTER_VALIDATE_URL)) {
        throw new EndUserException('Invalid return URL');
    }

    $scheme = $return !== null ? \parse_url($return, \PHP_URL_SCHEME) : null;
    if ($scheme !== null && ! \in_array($scheme, haystack: ['http', 'https'], strict: true)) {
        throw new EndUserException('Return URL must use http or https scheme');
    }

    if ($redirect && ! $return) {
        throw new EndUserException('"return" must be set when using "redirect"');
    }

    $c->rateLimiter()->check($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 'subscribe');

    $email = \is_string($_GET['email'] ?? null) ? $_GET['email'] : null;
    $feedUri = \is_string($_GET['uri'] ?? null) ? $_GET['uri'] : null;

    if ($email === null || $feedUri === null) {
        throw new EndUserException('Fields "email" and "uri" are required');
    }

    $c->subscriptions()->add($feedUri, $email);

    $responder->sendResponse($responseBuilder->fromString(
        sprintf('An email confirmation has been sent to %s.', $email),
        'Please check your inbox (and your spam folder).',
        $return,
    ));
} catch (EndUserException $endUserException) {
    $responder->sendResponse($responseBuilder->fromEndUserException($endUserException, $return));
}

exit();
