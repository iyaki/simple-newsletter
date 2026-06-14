<?php

declare(strict_types=1);

namespace SimpleNewsletter\Adapters;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * SMTP connection settings
 */
final readonly class SmtpConnection
{
    public function __construct(
        public string $host,
        public int $port,
        public string $encryption = PHPMailer::ENCRYPTION_STARTTLS,
        public bool $allowSelfSigned = false,
    ) {}
}
