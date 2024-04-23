<?php

declare(strict_types=1);

namespace SimpleNewsletter\Adapters;

use SimpleNewsletter\Components\Sender;
use SimpleNewsletter\Templates\Email\EmailInterface;

final readonly class SenderPHPMailer implements Sender
{
    public function __construct(
        private PHPMailerFactory $mailerFactory
    )
    {}

    public function send(EmailInterface $template): void
    {
        $mailer = $this->mailerFactory->create();

        foreach ($template->recipients() as $bcc) {
            $mailer->addBCC($bcc);
        }

        $mailer->isHTML();
        $mailer->Subject = $template->subject();
        $mailer->Body = $template->body();

        $mailer->send();
    }
}
