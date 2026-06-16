<?php

declare(strict_types=1);

namespace SimpleNewsletter\Adapters;

use PHPMailer\PHPMailer\PHPMailer;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Components\Sender;
use SimpleNewsletter\Templates\Email\EmailInterface;

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
        $mailer->SMTPSecure = $config->connection->encryption;
        $mailer->SMTPKeepAlive = true;
        $mailer->Host = $config->connection->host;
        $mailer->Port = $config->connection->port;
        $mailer->SMTPAuth = true;
        $mailer->Username = $config->credentials->user;
        $mailer->Password = $config->credentials->password;

        if ($config->connection->allowSelfSigned) {
            $mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
        }

        $mailer->setFrom($config->sender->from, 'Simple Newsletter');
        $mailer->addReplyTo($config->sender->replyTo, 'The Developer');

        $this->mailer = $mailer;
    }

    /**
     * @throws EndUserException
     */
    #[\Override]
    public function send(EmailInterface $template): void
    {
        try {
            $this->mailer->addAddress($template->recipient());
            $this->mailer->Subject = $template->subject();
            $this->mailer->isHTML();
            $body = \mb_convert_encoding($template->body(), to_encoding: '8bit');
            $this->mailer->Body = $body === false ? '' : $body;

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
