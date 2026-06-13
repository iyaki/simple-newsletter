<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use SimpleNewsletter\Adapters\SenderPHPMailer;
use SimpleNewsletter\Templates\Email\EmailInterface;

test('constructor sets up PHPMailer correctly', function () {
    $mailer = $this->createMock(PHPMailer::class);

    $mailer->expects($this->once())->method('isSMTP');
    $mailer->expects($this->once())->method('setFrom')->with('from@example.com', 'Simple Newsletter');
    $mailer->expects($this->once())->method('addReplyTo')->with('reply@example.com', 'The Developer');

    $sender = new SenderPHPMailer(
        host: 'smtp.example.com',
        port: 587,
        user: 'user',
        password: 'secret',
        from: 'from@example.com',
        replyTo: 'reply@example.com',
        mailer: $mailer,
    );

    expect($sender)->toBeInstanceOf(SenderPHPMailer::class);
});

test('send calls the expected sequence on PHPMailer', function () {
    $mailer = $this->createMock(PHPMailer::class);

    // Constructor expectations — these fire when SenderPHPMailer is instantiated
    $mailer->expects($this->once())->method('isSMTP');
    $mailer->expects($this->once())->method('setFrom');
    $mailer->expects($this->once())->method('addReplyTo');

    $sender = new SenderPHPMailer(
        host: 'smtp.example.com',
        port: 587,
        user: 'user',
        password: 'secret',
        from: 'from@example.com',
        replyTo: 'reply@example.com',
        mailer: $mailer,
    );

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
