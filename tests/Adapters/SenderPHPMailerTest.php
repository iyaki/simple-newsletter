<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use SimpleNewsletter\Adapters\SenderPHPMailer;
use SimpleNewsletter\Adapters\SmtpConfig;
use SimpleNewsletter\Adapters\SmtpConnection;
use SimpleNewsletter\Adapters\SmtpCredentials;
use SimpleNewsletter\Adapters\SmtpSender;
use SimpleNewsletter\Templates\Email\EmailInterface;

test('constructor sets up PHPMailer correctly', function (): void {
    $mailer = $this->createMock(PHPMailer::class);

    $mailer->expects($this->once())->method('isSMTP');
    $mailer->expects($this->once())->method('setFrom')->with('from@example.com', 'Simple Newsletter');
    $mailer->expects($this->once())->method('addReplyTo')->with('reply@example.com', 'The Developer');

    $config = new SmtpConfig(
        new SmtpConnection('smtp.example.com', 587, PHPMailer::ENCRYPTION_STARTTLS, false),
        new SmtpCredentials('user', 'secret'),
        new SmtpSender('from@example.com', 'reply@example.com'),
    );

    $sender = new SenderPHPMailer($config, $mailer);

    expect($sender)->toBeInstanceOf(SenderPHPMailer::class);
});

test('constructor sets up PHPMailer SMTP configuration', function (): void {
    $mailer = $this->createMock(PHPMailer::class);

    // Constructor expectations — these fire when SenderPHPMailer is instantiated
    $mailer->expects($this->once())->method('isSMTP');
    $mailer->expects($this->once())->method('setFrom');
    $mailer->expects($this->once())->method('addReplyTo');

    $config = new SmtpConfig(
        new SmtpConnection('smtp.example.com', 587, PHPMailer::ENCRYPTION_STARTTLS, false),
        new SmtpCredentials('user', 'secret'),
        new SmtpSender('from@example.com', 'reply@example.com'),
    );

    $sender = new SenderPHPMailer($config, $mailer);
});

test('send calls the expected sequence on PHPMailer', function (): void {
    $mailer = $this->createMock(PHPMailer::class);

    // Constructor expectations — these fire when SenderPHPMailer is instantiated
    $mailer->expects($this->once())->method('isSMTP');
    $mailer->expects($this->once())->method('setFrom');
    $mailer->expects($this->once())->method('addReplyTo');

    $config = new SmtpConfig(
        new SmtpConnection('smtp.example.com', 587, PHPMailer::ENCRYPTION_STARTTLS, false),
        new SmtpCredentials('user', 'secret'),
        new SmtpSender('from@example.com', 'reply@example.com'),
    );

    $sender = new SenderPHPMailer($config, $mailer);

    $email = $this->createMock(EmailInterface::class);
    $email->method('recipient')->willReturn('test@example.com');
    $email->method('subject')->willReturn('Test Subject');
    $email->method('body')->willReturn('<p>Body content</p>');

    // Send-method expectations
    $mailer->expects($this->once())->method('addAddress')->with('test@example.com');
    $mailer->expects($this->once())->method('isHTML');
    $mailer->expects($this->once())->method('send');
    $mailer->expects($this->once())->method('clearAllRecipients');
    $mailer->expects($this->once())->method('clearAttachments');

    $sender->send($email);
});
