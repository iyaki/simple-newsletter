<?php

declare(strict_types=1);

namespace SimpleNewsletter\Adapters;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * SMTP configuration for SenderPHPMailer
 */
final readonly class SmtpConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public string $user,
        #[\SensitiveParameter]
        public string $password,
        public string $from,
        public string $replyTo,
        public string $encryption = PHPMailer::ENCRYPTION_STARTTLS,
        public bool $allowSelfSigned = false,
    ) {}
}
