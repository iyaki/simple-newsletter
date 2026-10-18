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

    /**
     * For handled failures that are by design (external/user conditions the
     * app already copes with): same visibility, but warning-level in Sentry
     * so the error stream stays reserved for things needing action.
     */
    public static function expected(string $message, ?\Throwable $error = null): void
    {
        \error_log($message);

        \Sentry\withScope(static function (\Sentry\State\Scope $scope) use ($message, $error): void {
            $scope->setLevel(\Sentry\Severity::warning());

            if ($error !== null) {
                \Sentry\captureException($error);

                return;
            }

            \Sentry\captureMessage($message);
        });
    }
}
