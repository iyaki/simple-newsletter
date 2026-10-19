<?php

declare(strict_types=1);

namespace SimpleNewsletter\Components;

final class EndUserException extends \Exception
{
    /**
     * True when this wraps an infrastructure failure (PDO) that was converted
     * into a generic user-facing message at the DAO/RateLimiter boundary.
     * The user gets the sanitized text; ops still needs the signal in Sentry.
     */
    public function isTechnical(): bool
    {
        return $this->getPrevious() instanceof \PDOException;
    }

    /**
     * The most specific message in the wrapped chain: the technical reason
     * this user-facing exception was raised. For operator-facing reports
     * (cron logs, Sentry) — HTTP responses keep showing the sanitized text.
     */
    public function reason(): string
    {
        $deepest = $this;
        while (($previous = $deepest->getPrevious()) instanceof \Throwable) {
            $deepest = $previous;
        }

        return $deepest->getMessage();
    }
}
