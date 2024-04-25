<?php

declare(strict_types=1);

namespace SimpleNewsletter\Adapters;

use Laminas\Http\Header\KeepAlive;
use PHPMailer\PHPMailer\PHPMailer;
use SimpleNewsletter\Components\Sender;
use SimpleNewsletter\Templates\Email\EmailInterface;

final readonly class SenderPHPMailer implements Sender
{
    private readonly PHPMailer $mailer;

    public function __construct(
        string $host,
        int $port,
        string $user,
        string $password,
        string $from,
        string $replyTo
    ) {
        $mailer = new PHPMailer(true);

        $mailer->isSMTP();
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->SMTPKeepAlive = true;
        $mailer->Host = $host;
        $mailer->Port = $port;
        $mailer->SMTPAuth = true;
        $mailer->Username = $user;
        $mailer->Password = $password;

        $mailer->setFrom($from, 'Simple Newsletter');
        $mailer->addReplyTo($replyTo, 'The Developer');

        $this->mailer = $mailer;
    }

    public function send(EmailInterface $template): void
    {
        $this->mailer->addAddress($template->recipient());
        $this->mailer->Subject = $template->subject();
        $this->mailer->isHTML();
        $this->mailer->Body = $template->body();

        $this->mailer->send();

        $this->mailer->clearAllRecipients();
        $this->mailer->clearAttachments();
        $this->mailer->Subject = '';
        $this->mailer->Body = '';
    }
}
