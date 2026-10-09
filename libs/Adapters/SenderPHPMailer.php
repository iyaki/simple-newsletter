<?php

declare(strict_types=1);

namespace SimpleNewsletter\Adapters;

use PHPMailer\PHPMailer\PHPMailer;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Components\Sender;
use SimpleNewsletter\Templates\Email\EmailInterface;

/**
 * PHPMailer implementation of the Sender interface.
 *
 * Configures SMTP relay with PHPMailer, handles UTF-8 encoding and base64 transfer.
 */
final readonly class SenderPHPMailer implements Sender
{
    private PHPMailer $mailer;

    /**
     * @throws \PHPMailer\PHPMailer\Exception
     */
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

    /**
     * @throws EndUserException
     */
    #[\Override]
    public function send(EmailInterface $template): void
    {
        try {
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->Encoding = 'base64';
            $this->mailer->addAddress($template->recipient());
            $this->mailer->Subject = $template->subject();
            $this->mailer->isHTML();
            $this->mailer->Body = $template->body();

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
