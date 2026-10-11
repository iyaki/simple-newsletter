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
}
