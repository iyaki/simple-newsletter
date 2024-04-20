<?php

declare(strict_types=1);

namespace SimpleNewsletter\Models;

use PHPMailer\PHPMailer\PHPMailer;

interface Sender
{
    /**
     * @param string[] $to
     * @param string $message HTML body for the email
     */
    public function send(array $to, string $subject, string $message): void;
}
