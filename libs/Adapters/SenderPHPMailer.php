<?php

declare(strict_types=1);

namespace SimpleNewsletter\Adapters;

use PHPMailer\PHPMailer\PHPMailer;
use SimpleNewsletter\Components\Sender;
use SimpleNewsletter\Templates\Email\EmailInterface;

final readonly class SenderPHPMailer implements Sender
{
    private readonly PHPMailer $mailer;

    // mago-ignore
    public function __construct(
        string $host,
        int $port,
        string $user,
        #[\SensitiveParameter] string $password,
        string $from,
        string $replyTo,
        string $encryption = PHPMailer::ENCRYPTION_STARTTLS,
        bool $allowSelfSigned = false,
        ?PHPMailer $mailer = null,
    ) {
        $mailer ??= new PHPMailer(true);

        $mailer->isSMTP();
        $mailer->SMTPSecure = $encryption;
        $mailer->SMTPKeepAlive = true;
        $mailer->Host = $host;
        $mailer->Port = $port;
        $mailer->SMTPAuth = true;
        $mailer->Username = $user;
        $mailer->Password = $password;

        if ($allowSelfSigned) {
            $mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
        }

        $mailer->setFrom($from, 'Simple Newsletter');
        $mailer->addReplyTo($replyTo, 'The Developer');

        $this->mailer = $mailer;
    }

    public function send(EmailInterface $template): void
    {
        $this->mailer->addAddress($template->recipient());
        $this->mailer->Subject = $template->subject();
        $this->mailer->isHTML();
        $this->mailer->Body = \mb_convert_encoding($template->body(), '8bit');

        $this->mailer->send();

        $this->mailer->clearAllRecipients();
        $this->mailer->clearAttachments();
        $this->mailer->Subject = '';
        $this->mailer->Body = '';
    }
}
