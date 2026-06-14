<?php

declare(strict_types=1);

namespace SimpleNewsletter\Adapters;

/**
 * SMTP authentication credentials
 */
final readonly class SmtpCredentials
{
    public function __construct(
        public string $user,
        #[\SensitiveParameter]
        public string $password,
    ) {}
}
