<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use SimpleNewsletter\Adapters\SenderPHPMailer;
use SimpleNewsletter\Adapters\SmtpConfig;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Templates\Email\EmailInterface;

/**
 * @method \Mockery\ExpectationInterface once()
 * @method \Mockery\ExpectationInterface with(mixed ...$args)
 * @method \Mockery\ExpectationInterface andReturn(mixed $value)
 */
test(
    'constructor sets up PHPMailer correctly',
    /** @throws PHPMailerException */ function (): void {
        /** @var PHPMailer&\Mockery\MockInterface $mailer */
        $mailer = \Mockery::mock(PHPMailer::class);

        $mailer->shouldReceive('isSMTP')->once();
        $mailer->shouldReceive('setFrom')->with('from@example.com', 'Simple Newsletter')->once();
        $mailer->shouldReceive('addReplyTo')->with('reply@example.com', 'The Developer')->once();

        $config = new SmtpConfig(
            host: 'smtp.example.com',
            port: 587,
            user: 'user',
            password: 'secret',
            from: 'from@example.com',
            replyTo: 'reply@example.com',
        );

        $sender = new SenderPHPMailer($config, $mailer);

        expect($sender)->toBeInstanceOf(SenderPHPMailer::class);
    },
);

/**
 * @method \Mockery\ExpectationInterface once()
 * @method \Mockery\ExpectationInterface with(mixed ...$args)
 * @method \Mockery\ExpectationInterface andReturn(mixed $value)
 */
test(
    'constructor sets up PHPMailer SMTP configuration',
    /** @throws PHPMailerException */ function (): void {
        /** @var PHPMailer&\Mockery\MockInterface $mailer */
        $mailer = \Mockery::mock(PHPMailer::class);

        // Constructor expectations — these fire when SenderPHPMailer is instantiated
        $mailer->shouldReceive('isSMTP')->once();
        $mailer->shouldReceive('setFrom')->once();
        $mailer->shouldReceive('addReplyTo')->once();

        $config = new SmtpConfig(
            host: 'smtp.example.com',
            port: 587,
            user: 'user',
            password: 'secret',
            from: 'from@example.com',
            replyTo: 'reply@example.com',
        );

        $sender = new SenderPHPMailer($config, $mailer);
    },
);

/**
 * @method \Mockery\ExpectationInterface once()
 * @method \Mockery\ExpectationInterface with(mixed ...$args)
 * @method \Mockery\ExpectationInterface andReturn(mixed $value)
 */
test(
    'send calls the expected sequence on PHPMailer',
    /** @throws PHPMailerException|EndUserException */ function (): void {
        /** @var PHPMailer&\Mockery\MockInterface $mailer */
        $mailer = \Mockery::mock(PHPMailer::class);

        // Constructor expectations — these fire when SenderPHPMailer is instantiated
        $mailer->shouldReceive('isSMTP')->once();
        $mailer->shouldReceive('setFrom')->once();
        $mailer->shouldReceive('addReplyTo')->once();

        $config = new SmtpConfig(
            host: 'smtp.example.com',
            port: 587,
            user: 'user',
            password: 'secret',
            from: 'from@example.com',
            replyTo: 'reply@example.com',
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
    },
);
test(
    'send properly handles UTF-8 characters in subject and body',
    /** @throws PHPMailerException|EndUserException */ function (): void {
        /** @var PHPMailer&\Mockery\MockInterface $mailer */
        $mailer = \Mockery::mock(PHPMailer::class);

        // Constructor expectations
        $mailer->shouldReceive('isSMTP')->once();
        $mailer->shouldReceive('setFrom')->once();
        $mailer->shouldReceive('addReplyTo')->once();

        $config = new SmtpConfig(
            host: 'smtp.example.com',
            port: 587,
            user: 'user',
            password: 'secret',
            from: 'from@example.com',
            replyTo: 'reply@example.com',
        );

        $sender = new SenderPHPMailer($config, $mailer);

        /** @var EmailInterface&\Mockery\MockInterface $email */
        $email = \Mockery::mock(EmailInterface::class);
        $email->shouldReceive('recipient')->andReturn('test@example.com');
        $email->shouldReceive('subject')->andReturn("Jim Nielsen's Blog - Artículos diarios | iyaki");
        $email->shouldReceive('body')->andReturn('<p>Contenido con caracteres especiales: ñ, é, á, ó, í, ú</p>');

        // Send-method expectations
        $mailer->shouldReceive('addAddress')->with('test@example.com')->once();
        $mailer->shouldReceive('isHTML')->once();
        $mailer->shouldReceive('send')->once();
        $mailer->shouldReceive('clearAllRecipients')->once();
        $mailer->shouldReceive('clearAttachments')->once();

        $sender->send($email);

        expect(true)->toBeTrue();
    },
);

test(
    'a failed send drops the SMTP connection and clears state for the next recipient',
    /** @throws PHPMailerException|EndUserException */ function (): void {
        /** @var PHPMailer&\Mockery\MockInterface $mailer */
        $mailer = \Mockery::mock(PHPMailer::class);

        // Constructor expectations
        $mailer->shouldReceive('isSMTP')->once();
        $mailer->shouldReceive('setFrom')->once();
        $mailer->shouldReceive('addReplyTo')->once();

        $config = new SmtpConfig(
            host: 'smtp.example.com',
            port: 587,
            user: 'user',
            password: 'secret',
            from: 'from@example.com',
            replyTo: 'reply@example.com',
        );

        $sender = new SenderPHPMailer($config, $mailer);

        /** @var EmailInterface&\Mockery\MockInterface $email */
        $email = \Mockery::mock(EmailInterface::class);
        $email->shouldReceive('recipient')->twice()->andReturn('first@example.com', 'second@example.com');
        $email->shouldReceive('subject')->twice()->andReturn('Test Subject');
        $email->shouldReceive('body')->twice()->andReturn('<p>Body content</p>');

        $mailer->shouldReceive('addAddress')->with('first@example.com')->once();
        $mailer->shouldReceive('addAddress')->with('second@example.com')->once();
        $mailer->shouldReceive('isHTML')->twice();
        // First send fails (recipient rejected), second send succeeds.
        $recipientsFailed = new PHPMailerException('SMTP Error: The following recipients failed: first@example.com');
        $sendCalls = 0;
        $mailer->shouldReceive('send')->twice()->andReturnUsing(function () use (&$sendCalls, $recipientsFailed): void {
            if (++$sendCalls === 1) {
                throw $recipientsFailed;
            }
        });
        // Failure path must drop the poisoned keepalive connection...
        $mailer->shouldReceive('smtpClose')->once();
        // ...and the finally block must clean up on both the failed and the
        // successful send, so no recipient from a previous send leaks in.
        $mailer->shouldReceive('clearAllRecipients')->twice();
        $mailer->shouldReceive('clearAttachments')->twice();

        $caught = null;
        try {
            $sender->send($email);
        } catch (EndUserException $exception) {
            $caught = $exception;
        }
        expect($caught)->toBeInstanceOf(EndUserException::class);

        // Second send must run against a clean mailer, carrying only its own recipient.
        $sender->send($email);
    },
);


