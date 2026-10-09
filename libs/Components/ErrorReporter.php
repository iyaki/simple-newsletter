<?php

declare(strict_types=1);

namespace SimpleNewsletter\Components;

/**
 * Single choke point for operational failure reporting: always writes to the
 * server log, and forwards the error to Sentry when a DSN is configured
 * (capture* calls are no-ops without one).
 */
final class ErrorReporter
{
    public static function report(string $message, ?\Throwable $error = null): void
    {
        \error_log($message);

        if ($error !== null) {
            \Sentry\captureException($error);

            return;
        }

        \Sentry\captureMessage($message, \Sentry\Severity::warning());
    }
}
