<?php

declare(strict_types=1);

namespace SimpleNewsletter\Adapters;

/**
 * SMTP configuration for SenderPHPMailer
 */
final readonly class SmtpConfig
{
    public function __construct(
        public SmtpConnection $connection,
        public SmtpCredentials $credentials,
        public SmtpSender $sender,
    ) {}
}
