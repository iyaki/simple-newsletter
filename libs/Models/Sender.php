<?php

declare(strict_types=1);

namespace SimpleNewsletter\Models;

use PHPMailer\PHPMailer\PHPMailer;

final class Sender
{
    public function subscriptionConfirmation(string $to, string $newsletterName, string $confirmationUri): void
    {
        $this->send(
            [$to],
            'Simple Newsletter - Subscription Confirmation',
            <<<HTML
            <p>Hi, to confirm your email newsletter subscription to {$newsletterName} click on this link: {$confirmationUri} or copy and paste the url from below in your browser: {$confirmationUri}.</p>
            <p>If you didn't request this email you can safely ignore it.</p>
            HTML
        );
    }

    public function newsletterPost(array $to, Post $post): void
    {

    }

    /**
     * @param string[] $to
     * @param string $message HTML body for the email
     */
    private function send(array $to, string $subject, string $message): void
    {
        // TODO: Cambiar los getenv para que lleguen mediante el constructor
        try {
            $mailer = new PHPMailer(true);
            $mailer->isSMTP();
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->Host = \getenv('SMTP_HOST');
            $mailer->Port = \getenv('SMTP_PORT');
            $mailer->SMTPAuth = true;
            $mailer->Username = \getenv('SMTP_USER');
            $mailer->Password = \getenv('SMTP_PASSWORD');

            $mailer->setFrom(\getenv('EMAIL_FROM'), 'Simple Newsletter');
            // $mailer->addAddress('joe@example.net', 'Joe User');     //Add a recipient
            // $mailer->addAddress('ellen@example.com');               //Name is optional
            $mailer->addReplyTo(\getenv('EMAIL_REPLY_TO'), 'The Developer');
            // $mailer->addCC('cc@example.com');
            foreach ($to as $bcc) {
                $mailer->addBCC($bcc);
            }

            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = $message;

            $mailer->send();
        } catch (\Throwable $e) {
            throw new \Exception("Message could not be sent. Mailer Error: {$mailer->ErrorInfo}", 0, $e);
        }
    }
}
