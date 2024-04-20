<?php

declare(strict_types=1);

namespace SimpleNewsletter\Adapters;

use PHPMailer\PHPMailer\PHPMailer;

final readonly class PHPMailerFactory
{
    public function __construct(
        private string $host,
        private int $port,
        private string $user,
        private string $password,
        private string $from,
        private string $replyTo
    ) { }

    public function create(): PHPMailer
    {
        $mailer = new PHPMailer(true);

        $mailer->isSMTP();
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Host = $this->host;
        $mailer->Port = $this->port;
        $mailer->SMTPAuth = true;
        $mailer->Username = $this->user;
        $mailer->Password = $this->password;

        $mailer->setFrom($this->from, 'Simple Newsletter');
        $mailer->addReplyTo($this->replyTo, 'The Developer');

        return $mailer;
    }
}
