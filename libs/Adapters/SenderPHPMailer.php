<?php

declare(strict_types=1);

namespace SimpleNewsletter\Adapters;

use PHPMailer\PHPMailer\PHPMailer;
use SimpleNewsletter\Components\Sender;
use SimpleNewsletter\Templates\Email\EmailInterface;

final readonly class SenderPHPMailer implements Sender
{
    private PHPMailer $mailer;

    public function __construct(
        SmtpConfig $config,
        ?PHPMailer $mailer = null,
    ) {
        $mailer ??= new PHPMailer(true);

        $mailer->isSMTP();
        $mailer->SMTPSecure = $config->encryption;
        $mailer->SMTPKeepAlive = true;
        $mailer->Host = $config->host;
        $mailer->Port = $config->port;
        $mailer->SMTPAuth = true;
        $mailer->Username = $config->user;
        $mailer->Password = $config->password;

        if ($config->allowSelfSigned) {
            $mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
        }

        $mailer->setFrom($config->from, 'Simple Newsletter');
        $mailer->addReplyTo($config->replyTo, 'The Developer');

        $this->mailer = $mailer;
    }

    #[\Override]
    public function send(EmailInterface $template): void
    {
        try {
            $this->mailer->addAddress($template->recipient());
            $this->mailer->Subject = $template->subject();
            $this->mailer->isHTML();
            $this->mailer->Body = \mb_convert_encoding($template->body(), encoding: '8bit');

            $this->mailer->send();

            $this->mailer->clearAllRecipients();
            $this->mailer->clearAttachments();
            $this->mailer->Subject = '';
            $this->mailer->Body = '';
        } catch (\Exception $exception) {
            throw new EndUserException(
                'We could not send the confirmation email. Please check your email address or contact support.',
                0,
                $exception,
            );
        }
    }
}
