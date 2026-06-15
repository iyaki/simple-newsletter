<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use SimpleNewsletter\Adapters\SenderPHPMailer;
use SimpleNewsletter\Adapters\SmtpConfig;
use SimpleNewsletter\Adapters\SmtpConnection;
use SimpleNewsletter\Adapters\SmtpCredentials;
use SimpleNewsletter\Adapters\SmtpSender;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Templates\Email\EmailInterface;

/**
 * @method \Mockery\ExpectationInterface once()
 * @method \Mockery\ExpectationInterface with(mixed ...$args)
 * @method \Mockery\ExpectationInterface andReturn(mixed $value)
 */
test('constructor sets up PHPMailer correctly', /** @throws PHPMailerException */ function (): void {
    /** @var PHPMailer&\Mockery\MockInterface $mailer */
    $mailer = \Mockery::mock(PHPMailer::class);

    $mailer->shouldReceive('isSMTP')->once();
    $mailer->shouldReceive('setFrom')->with('from@example.com', 'Simple Newsletter')->once();
    $mailer->shouldReceive('addReplyTo')->with('reply@example.com', 'The Developer')->once();

    $config = new SmtpConfig(
        new SmtpConnection('smtp.example.com', 587, PHPMailer::ENCRYPTION_STARTTLS, false),
        new SmtpCredentials('user', 'secret'),
        new SmtpSender('from@example.com', 'reply@example.com'),
    );

    $sender = new SenderPHPMailer($config, $mailer);

    expect($sender)->toBeInstanceOf(SenderPHPMailer::class);
});

/**
 * @method \Mockery\ExpectationInterface once()
 * @method \Mockery\ExpectationInterface with(mixed ...$args)
 * @method \Mockery\ExpectationInterface andReturn(mixed $value)
 */
test('constructor sets up PHPMailer SMTP configuration', /** @throws PHPMailerException */ function (): void {
    /** @var PHPMailer&\Mockery\MockInterface $mailer */
    $mailer = \Mockery::mock(PHPMailer::class);

    // Constructor expectations — these fire when SenderPHPMailer is instantiated
    $mailer->shouldReceive('isSMTP')->once();
    $mailer->shouldReceive('setFrom')->once();
    $mailer->shouldReceive('addReplyTo')->once();

    $config = new SmtpConfig(
        new SmtpConnection('smtp.example.com', 587, PHPMailer::ENCRYPTION_STARTTLS, false),
        new SmtpCredentials('user', 'secret'),
        new SmtpSender('from@example.com', 'reply@example.com'),
    );

    $sender = new SenderPHPMailer($config, $mailer);
});

/**
 * @method \Mockery\ExpectationInterface once()
 * @method \Mockery\ExpectationInterface with(mixed ...$args)
 * @method \Mockery\ExpectationInterface andReturn(mixed $value)
 */
#[\AllowDynamicProperties]
test('send calls the expected sequence on PHPMailer', /** @throws PHPMailerException|EndUserException */ function (): void {
    /** @var PHPMailer&\Mockery\MockInterface $mailer */
    $mailer = \Mockery::mock(PHPMailer::class);

    // Constructor expectations — these fire when SenderPHPMailer is instantiated
    $mailer->shouldReceive('isSMTP')->once();
    $mailer->shouldReceive('setFrom')->once();
    $mailer->shouldReceive('addReplyTo')->once();

    $config = new SmtpConfig(
        new SmtpConnection('smtp.example.com', 587, PHPMailer::ENCRYPTION_STARTTLS, false),
        new SmtpCredentials('user', 'secret'),
        new SmtpSender('from@example.com', 'reply@example.com'),
    );

    $sender = new SenderPHPMailer($config, $mailer);

    /** @var EmailInterface&\Mockery\MockInterface $email */
    $email = \Mockery::mock(EmailInterface::class);
    $email->shouldReceive('recipient')->andReturn('test@example.com');
    $email->shouldReceive('subject')->andReturn('Test Subject');
    $email->shouldReceive('body')->andReturn('<p>Body content</p>');

    // Send-method expectations
    $mailer->shouldReceive('addAddress')->with('test@example.com')->once();
    $mailer->shouldReceive('isHTML')->once();
    $mailer->shouldReceive('send')->once();
    $mailer->shouldReceive('clearAllRecipients')->once();
    $mailer->shouldReceive('clearAttachments')->once();

    $sender->send($email);
});
