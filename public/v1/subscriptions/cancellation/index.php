<?php

declare(strict_types=1);

namespace SimpleNewsletter;

use PHPMailer\PHPMailer\Exception;
use SimpleNewsletter\Components\EndUserException;

/**
 * A fail-closed configuration error (missing SECRET_KEY) is rendered as a
 * 500: the service must not serve with forgeable tokens.
 */
(static function (): never {
    $c = new Container();
    $responder = $c->responder();
    $responseBuilder = $responder->responseBuilderFromContentNegotiation($_SERVER['HTTP_ACCEPT'] ?? '');

    header('X-Robots-Tag: noindex, nofollow');

    try {
        $email = \is_string($_GET['email'] ?? null) ? $_GET['email'] : null;
        $feedUri = \is_string($_GET['uri'] ?? null) ? $_GET['uri'] : null;
        $token = \is_string($_GET['token'] ?? null) ? $_GET['token'] : null;

        if ($email === null || $feedUri === null || $token === null) {
            throw new EndUserException('Fields "email", "uri" and "token" are required');
        }

        $c->subscriptions()->cancel($feedUri, $email, $token);

        $responder->sendResponse($responseBuilder->fromString('Subscription successfully cancelled.', ''));
    } catch (EndUserException $endUserException) {
        $responder->sendResponse($responseBuilder->fromEndUserException($endUserException));
    } catch (\PDOException|Exception $technicalException) {
        \SimpleNewsletter\Components\ErrorReporter::report(
            'Technical error: ' . $technicalException->getMessage(),
            $technicalException,
        );
        $responder->sendResponse($responseBuilder->fromEndUserException(new EndUserException(
            'A technical error occurred. Please try again later.',
            0,
            $technicalException,
        )));
    } catch (\RuntimeException $configurationException) {
        // Fail closed: a misconfigured deployment (e.g. missing SECRET_KEY)
        // must not fall back to serving with forgeable tokens.
        \SimpleNewsletter\Components\ErrorReporter::report(
            'Configuration error: ' . $configurationException->getMessage(),
            $configurationException,
        );
        $responder->sendResponse($responseBuilder->fromString(
            'Error: Internal server error',
            'A technical error occurred. Please try again later.',
        ), 500);
    }

    exit();
})();
