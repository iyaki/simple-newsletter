<?php

declare(strict_types=1);

namespace SimpleNewsletter\Adapters;

/**
 * Email sender configuration
 */
final readonly class SmtpSender
{
    public function __construct(
        public string $from,
        public string $replyTo,
    ) {}
}
