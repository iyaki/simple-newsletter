<?php

declare(strict_types=1);

use SimpleNewsletter\Adapters\SenderPHPMailer;
use SimpleNewsletter\Components\Auth;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\FeedMetadata;
use SimpleNewsletter\Data\Post;
use SimpleNewsletter\Data\Subscription;
use SimpleNewsletter\Models\Newsletter;
use SimpleNewsletter\Templates\Email\EmailInterface;
use SimpleNewsletter\Templates\Email\Newsletter as NewsletterTemplate;
use SimpleNewsletter\Templates\Email\SubscriptionConfirmation;

test('sendConfirmation sends a SubscriptionConfirmation containing the confirmation token', function (): void {
    /** @var SubscriptionConfirmation|null $captured */
    $captured = null;
    /** @var SenderPHPMailer&\Mockery\MockInterface $sender */
    $sender = \Mockery::mock(SenderPHPMailer::class);
    $sender->shouldReceive('send')->once()->andReturnUsing(function (EmailInterface $template) use (&$captured): void {
        $captured = $template;
    });
    /** @var Auth&\Mockery\MockInterface $auth */
    $auth = \Mockery::mock(Auth::class);

    $now = new DateTimeImmutable();
    $feed = new Feed(new FeedMetadata('https://example.com/feed', 'Test Feed', 'https://example.com', $now));
    $subscription = new Subscription('https://example.com/feed', 'user@example.com');

    $token = 'generated-token';
    $auth->shouldReceive('tokenKey')->with('confirm', 'https://example.com/feed', 'user@example.com', '')->once()->andReturn('confirm|https://example.com/feed|user@example.com|');
    $auth->shouldReceive('hash')->with('confirm|https://example.com/feed|user@example.com|')->once()->andReturn($token);

    $newsletter = new Newsletter($sender, 'https://example.com', $auth);
    $newsletter->sendConfirmation($feed, $subscription);

    \assert($captured instanceof SubscriptionConfirmation);
    expect($captured->recipient())->toBe('user@example.com')
        ->and($captured->body())->toContain(\urlencode($token))
        ->and($captured->body())->toContain(\urlencode('https://example.com/feed'))
        ->and($captured->body())->toContain('/v1/subscriptions/confirmation/');
});

test('sendConfirmation uses the action-scoped token key as MAC input', function (): void {
    /** @var SubscriptionConfirmation|null $captured */
    $captured = null;
    /** @var SenderPHPMailer&\Mockery\MockInterface $sender */
    $sender = \Mockery::mock(SenderPHPMailer::class);
    $sender->shouldReceive('send')->once()->andReturnUsing(function (EmailInterface $template) use (&$captured): void {
        $captured = $template;
    });
    /** @var Auth&\Mockery\MockInterface $auth */
    $auth = \Mockery::mock(Auth::class);

    $now = new DateTimeImmutable();
    $feed = new Feed(new FeedMetadata('https://example.com/feed', 'Test Feed', 'https://example.com', $now));
    $subscription = new Subscription('https://example.com/feed', 'user@example.com');

    $expectedToken = 'hash-of-email';

    $auth->shouldReceive('tokenKey')->with('confirm', 'https://example.com/feed', 'user@example.com', '')->once()->andReturn('confirm|https://example.com/feed|user@example.com|');
    $auth->shouldReceive('hash')->with('confirm|https://example.com/feed|user@example.com|')->once()->andReturn($expectedToken);

    $newsletter = new Newsletter($sender, 'https://example.com', $auth);
    $newsletter->sendConfirmation($feed, $subscription);

    \assert($captured instanceof SubscriptionConfirmation);
    expect($captured->body())->toContain(\urlencode($expectedToken));
});

test('sendPostsToSubscribers sends a template per subscription', function (): void {
    /** @var list<NewsletterTemplate> $captured */
    $captured = [];
    /** @var SenderPHPMailer&\Mockery\MockInterface $sender */
    $sender = \Mockery::mock(SenderPHPMailer::class);
    $sender->shouldReceive('send')->twice()->andReturnUsing(function (EmailInterface $template) use (&$captured): void {
        \assert($template instanceof NewsletterTemplate);
        $captured[] = $template;
    });
    /** @var Auth&\Mockery\MockInterface $auth */
    $auth = \Mockery::mock(Auth::class);

    $now = new DateTimeImmutable();
    $feed = new Feed(new FeedMetadata('https://example.com/feed', 'Test Feed', 'https://example.com', $now));
    $post = new Post('https://example.com/post1', 'Post 1', 'Content 1');

    $sub1 = new Subscription('https://example.com/feed', 'user1@example.com');
    $sub2 = new Subscription('https://example.com/feed', 'user2@example.com');

    $auth->shouldReceive('tokenKey')->twice()->andReturn('key1', 'key2');
    $auth->shouldReceive('hash')->twice()->andReturn('token1', 'token2');

    $newsletter = new Newsletter($sender, 'https://example.com', $auth);
    $newsletter->sendPostsToSubscribers($feed, [$post], $sub1, $sub2);

    \assert(isset($captured[0], $captured[1]));
    expect(\count($captured))->toBe(2)
        ->and($captured[0]->recipient())->toBe('user1@example.com')
        ->and($captured[1]->recipient())->toBe('user2@example.com');
});

test('sendPostsToSubscribers creates correct template per subscription', function (): void {
    /** @var list<NewsletterTemplate> $captured */
    $captured = [];
    /** @var SenderPHPMailer&\Mockery\MockInterface $sender */
    $sender = \Mockery::mock(SenderPHPMailer::class);
    $sender->shouldReceive('send')->once()->andReturnUsing(function (EmailInterface $template) use (&$captured): void {
        \assert($template instanceof NewsletterTemplate);
        $captured[] = $template;
    });
    /** @var Auth&\Mockery\MockInterface $auth */
    $auth = \Mockery::mock(Auth::class);

    $now = new DateTimeImmutable();
    $feed = new Feed(new FeedMetadata('https://example.com/feed', 'Test Feed', 'https://example.com', $now));
    $post = new Post('https://example.com/post/1', 'Test Post Title', '<p>Test content</p>');

    $sub = new Subscription('https://example.com/feed', 'alice@example.com');

    $auth->shouldReceive('tokenKey')->andReturn('token-key');
    $auth->shouldReceive('hash')->andReturn($cancelToken = 'token-for-alice');

    $newsletter = new Newsletter($sender, 'https://example.com', $auth);
    $newsletter->sendPostsToSubscribers($feed, [$post], $sub);

    \assert(isset($captured[0]));
    expect($captured[0]->recipient())->toBe('alice@example.com')
        ->and($captured[0]->subject())->toBe('Test Post Title - Test Feed')
        ->and($captured[0]->body())->toContain(\urlencode($cancelToken))
        ->and($captured[0]->body())->toContain('/v1/subscriptions/cancellation/');
});

test('sendPostsToSubscribers continues after a recipient send failure', function (): void {
    /** @var list<NewsletterTemplate> $captured */
    $captured = [];
    $calls = 0;
    /** @var SenderPHPMailer&\Mockery\MockInterface $sender */
    $sender = \Mockery::mock(SenderPHPMailer::class);
    $sender->shouldReceive('send')->times(3)->andReturnUsing(function (EmailInterface $template) use (&$captured, &$calls): void {
        \assert($template instanceof NewsletterTemplate);
        $calls++;
        if ($calls === 1) {
            throw new EndUserException('SMTP relay unavailable');
        }
        $captured[] = $template;
    });
    /** @var Auth&\Mockery\MockInterface $auth */
    $auth = \Mockery::mock(Auth::class);

    $now = new DateTimeImmutable();
    $feed = new Feed(new FeedMetadata('https://example.com/feed', 'Test Feed', 'https://example.com', $now));
    $post = new Post('https://example.com/post1', 'Post 1', 'Content 1');

    $sub1 = new Subscription('https://example.com/feed', 'user1@example.com');
    $sub2 = new Subscription('https://example.com/feed', 'user2@example.com');
    $sub3 = new Subscription('https://example.com/feed', 'user3@example.com');

    $auth->shouldReceive('tokenKey')->times(3)->andReturn('key1', 'key2', 'key3');
    $auth->shouldReceive('hash')->times(3)->andReturn('token1', 'token2', 'token3');

    $newsletter = new Newsletter($sender, 'https://example.com', $auth);
    $newsletter->sendPostsToSubscribers($feed, [$post], $sub1, $sub2, $sub3);

    \assert(isset($captured[0], $captured[1]));
    expect(\count($captured))->toBe(2)
        ->and($captured[0]->recipient())->toBe('user2@example.com')
        ->and($captured[1]->recipient())->toBe('user3@example.com');
});

test('sendPostsToSubscribers reports failures without subscriber PII', function (): void {
    $previousErrorLog = \ini_get('error_log');
    $logFile = \tempnam(\sys_get_temp_dir(), 'newsletter-');
    \assert(\is_string($logFile));
    \ini_set('error_log', $logFile);

    try {
        /** @var list<NewsletterTemplate> $captured */
        $captured = [];
        $calls = 0;
        /** @var SenderPHPMailer&\Mockery\MockInterface $sender */
        $sender = \Mockery::mock(SenderPHPMailer::class);
        $sender->shouldReceive('send')->twice()->andReturnUsing(function (EmailInterface $template) use (&$captured, &$calls): void {
            \assert($template instanceof NewsletterTemplate);
            $calls++;
            if ($calls === 1) {
                throw new EndUserException('SMTP relay unavailable');
            }
            $captured[] = $template;
        });
        /** @var Auth&\Mockery\MockInterface $auth */
        $auth = \Mockery::mock(Auth::class);

        $now = new DateTimeImmutable();
        $feed = new Feed(new FeedMetadata('https://example.com/feed', 'Test Feed', 'https://example.com', $now));
        $post = new Post('https://example.com/post1', 'Post 1', 'Content 1');

        $sub1 = new Subscription('https://example.com/feed', 'user1@example.com');
        $sub2 = new Subscription('https://example.com/feed', 'user2@example.com');

        $auth->shouldReceive('tokenKey')->times(2)->andReturn('key1', 'key2');
        $auth->shouldReceive('hash')->times(2)->andReturn('token1', 'token2');

        $newsletter = new Newsletter($sender, 'https://example.com', $auth);
        $newsletter->sendPostsToSubscribers($feed, [$post], $sub1, $sub2);

        \assert(isset($captured[0]));
        expect(\count($captured))->toBe(1)
            ->and($captured[0]->recipient())->toBe('user2@example.com');

        $log = \file_get_contents($logFile);
        \assert(\is_string($log));
        expect(\substr_count($log, 'Delivery to subscriber of https://example.com/feed failed: SMTP relay unavailable'))->toBe(1)
            ->and($log)->not->toContain('user1@example.com');
    } finally {
        \ini_set('error_log', \is_string($previousErrorLog) ? $previousErrorLog : '');
        \unlink($logFile);
    }
});

test('sendPostsToSubscribers rethrows when every recipient send fails', function (): void {
    $calls = 0;
    /** @var SenderPHPMailer&\Mockery\MockInterface $sender */
    $sender = \Mockery::mock(SenderPHPMailer::class);
    $sender->shouldReceive('send')->twice()->andReturnUsing(function () use (&$calls): void {
        $calls++;
        if ($calls === 1) {
            throw new EndUserException('SMTP relay unavailable');
        }
        throw new EndUserException('Connection refused');
    });
    /** @var Auth&\Mockery\MockInterface $auth */
    $auth = \Mockery::mock(Auth::class);

    $now = new DateTimeImmutable();
    $feed = new Feed(new FeedMetadata('https://example.com/feed', 'Test Feed', 'https://example.com', $now));
    $post = new Post('https://example.com/post1', 'Post 1', 'Content 1');

    $sub1 = new Subscription('https://example.com/feed', 'user1@example.com');
    $sub2 = new Subscription('https://example.com/feed', 'user2@example.com');

    $auth->shouldReceive('tokenKey')->times(2)->andReturn('key1', 'key2');
    $auth->shouldReceive('hash')->times(2)->andReturn('token1', 'token2');

    $newsletter = new Newsletter($sender, 'https://example.com', $auth);
    $newsletter->sendPostsToSubscribers($feed, [$post], $sub1, $sub2);
})->throws(EndUserException::class, 'Connection refused');

test('sendPostsToSubscribers returns normally on partial failure', function (): void {
    /** @var list<NewsletterTemplate> $captured */
    $captured = [];
    $calls = 0;
    /** @var SenderPHPMailer&\Mockery\MockInterface $sender */
    $sender = \Mockery::mock(SenderPHPMailer::class);
    $sender->shouldReceive('send')->twice()->andReturnUsing(function (EmailInterface $template) use (&$captured, &$calls): void {
        \assert($template instanceof NewsletterTemplate);
        $calls++;
        if ($calls === 1) {
            throw new EndUserException('SMTP relay unavailable');
        }
        $captured[] = $template;
    });
    /** @var Auth&\Mockery\MockInterface $auth */
    $auth = \Mockery::mock(Auth::class);

    $now = new DateTimeImmutable();
    $feed = new Feed(new FeedMetadata('https://example.com/feed', 'Test Feed', 'https://example.com', $now));
    $post = new Post('https://example.com/post1', 'Post 1', 'Content 1');

    $sub1 = new Subscription('https://example.com/feed', 'user1@example.com');
    $sub2 = new Subscription('https://example.com/feed', 'user2@example.com');

    $auth->shouldReceive('tokenKey')->times(2)->andReturn('key1', 'key2');
    $auth->shouldReceive('hash')->times(2)->andReturn('token1', 'token2');

    $newsletter = new Newsletter($sender, 'https://example.com', $auth);
    $newsletter->sendPostsToSubscribers($feed, [$post], $sub1, $sub2);

    \assert(isset($captured[0]));
    expect(\count($captured))->toBe(1)
        ->and($captured[0]->recipient())->toBe('user2@example.com');
});
