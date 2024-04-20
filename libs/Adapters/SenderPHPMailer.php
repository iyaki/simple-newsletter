<?php

declare(strict_types=1);

namespace SimpleNewsletter\Adapters;

use SimpleNewsletter\Models\Sender;

final class SenderPHPMailer implements Sender
{
    public function __construct(
        private PHPMailerFactory $mailerFactory
    )
    {}

    public function send(array $to, string $subject, string $message): void
    {
        try {
            $mailer = $this->mailerFactory->create();

            foreach ($to as $bcc) {
                $mailer->addBCC($bcc);
            }

            $mailer->isHTML();
            $mailer->Subject = $subject;
            $mailer->Body = $message;

            $mailer->send();
        } catch (\Throwable $e) {
            throw new \Exception("Message could not be sent. Mailer Error: {$mailer->ErrorInfo}", 0, $e);
        }


    }
}
