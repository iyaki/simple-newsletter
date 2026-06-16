<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use SimpleNewsletter\Components\Auth;
use SimpleNewsletter\Components\EmailTemplateFactory;
use SimpleNewsletter\Components\Sender;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\FeedMetadata;
use SimpleNewsletter\Data\Post;
use SimpleNewsletter\Data\Subscription;
use SimpleNewsletter\Models\Newsletter;
use SimpleNewsletter\Templates\Email\Newsletter as NewsletterTemplate;
use SimpleNewsletter\Templates\Email\SubscriptionConfirmation;

/**
 * @throws \InvalidArgumentException
 */
test('sendConfirmation calls sender with template from EmailTemplateFactory', function (): void {
    /** @var Sender&\Mockery\MockInterface $sender */
    $sender = \Mockery::mock(Sender::class);
    $emailTemplateFactory = \Mockery::mock(EmailTemplateFactory::class);
    $auth = \Mockery::mock(Auth::class);

    $now = new DateTimeImmutable();
    $feed = new Feed(new FeedMetadata('https://example.com/feed', 'Test Feed', 'https://example.com', $now));
    $subscription = new Subscription('https://example.com/feed', 'user@example.com');

    $token = 'generated-token';
    $template = new SubscriptionConfirmation(
        'user@example.com',
        $feed,
        'https://example.com/confirm?token=generated-token',
    );

    $auth->shouldReceive('hash')->with('user@example.com')->once()->andReturn($token);

    $emailTemplateFactory
        ->shouldReceive('createConfirmation')
        ->with($subscription, $feed, $token)
        ->once()
        ->andReturn($template);

    $sender->shouldReceive('send')->with($template)->once();

    $newsletter = new Newsletter($sender, $emailTemplateFactory, $auth);
    $newsletter->sendConfirmation($feed, $subscription);
});

/**
 * @throws AssertionFailedError
 */
test('sendConfirmation uses auth hash of subscription email as token', function (): void {
    $sender = \Mockery::mock(Sender::class);
    $emailTemplateFactory = \Mockery::mock(EmailTemplateFactory::class);
    $auth = \Mockery::mock(Auth::class);

    $now = new DateTimeImmutable();
    $feed = new Feed(new FeedMetadata('https://example.com/feed', 'Test Feed', 'https://example.com', $now));
    $subscription = new Subscription('https://example.com/feed', 'user@example.com');

    $expectedToken = 'hash-of-email';

    $auth->shouldReceive('hash')->with('user@example.com')->once()->andReturn($expectedToken);

    $emailTemplateFactory
        ->shouldReceive('createConfirmation')
        ->with(\Mockery::any(), \Mockery::any(), $expectedToken)
        ->once()
        ->andReturn(new SubscriptionConfirmation('user@example.com', $feed, 'https://example.com/confirm'));

    $sender->shouldReceive('send')->once();
    $newsletter = new Newsletter($sender, $emailTemplateFactory, $auth);
    $newsletter->sendConfirmation($feed, $subscription);
});

/**
 * @throws AssertionFailedError
 * @throws \InvalidArgumentException
 */
test('sendPostToSubscribers calls sender for each subscription', function (): void {
    $sender = \Mockery::mock(Sender::class);
    $emailTemplateFactory = \Mockery::mock(EmailTemplateFactory::class);
    $auth = \Mockery::mock(Auth::class);

    $now = new DateTimeImmutable();
    $feed = new Feed(new FeedMetadata('https://example.com/feed', 'Test Feed', 'https://example.com', $now));
    $post = new Post('https://example.com/post1', 'Post 1', 'Content 1');

    $sub1 = new Subscription('https://example.com/feed', 'user1@example.com');
    $sub2 = new Subscription('https://example.com/feed', 'user2@example.com');

    $template1 = new NewsletterTemplate($sub1, $feed, $post, 'https://example.com/cancel/user1');
    $template2 = new NewsletterTemplate($sub2, $feed, $post, 'https://example.com/cancel/user2');

    $auth
        ->shouldReceive('hash')
        ->times(2)
        ->andReturnUsing(fn (string $email): string => match ($email) {
            'user1@example.com' => 'token1',
            'user2@example.com' => 'token2',
            default => throw new \InvalidArgumentException('Unexpected email: ' . $email),
        });

    $emailTemplateFactory
        ->shouldReceive('createNewsletter')
        ->times(2)
        ->andReturnUsing(fn (
            Subscription $sub,
            Feed $_feed,
            Post $_post,
            #[\SensitiveParameter]
            string $_token,
        ) => match ($sub->email) {
            'user1@example.com' => $template1,
            'user2@example.com' => $template2,
            default => throw new \InvalidArgumentException('Unexpected subscription email: ' . $sub->email),
        });

    $invocations = [];
    $sender
        ->shouldReceive('send')
        ->times(2)
        ->andReturnUsing(fn (NewsletterTemplate $template) => $invocations[] = $template);

    $newsletter = new Newsletter($sender, $emailTemplateFactory, $auth);
    $newsletter->sendPostToSubscribers($feed, $post, $sub1, $sub2);

    expect($invocations)->toHaveCount(2);
    \assert($invocations[0] !== null, 'first invocation should exist');
    \assert($invocations[1] !== null, 'second invocation should exist');
    expect($invocations[0])->toBe($template1);
    expect($invocations[1])->toBe($template2);
});

/**
 * @throws AssertionFailedError
 */
test('sendPostToSubscribers creates correct template per subscription', function (): void {
    $sender = \Mockery::mock(Sender::class);
    $emailTemplateFactory = \Mockery::mock(EmailTemplateFactory::class);
    $auth = \Mockery::mock(Auth::class);

    $now = new DateTimeImmutable();
    $feed = new Feed(new FeedMetadata('https://example.com/feed', 'Test Feed', 'https://example.com', $now));
    $post = new Post('https://example.com/post1', 'Post 1', 'Content 1');

    $sub = new Subscription('https://example.com/feed', 'alice@example.com');

    $template = new NewsletterTemplate($sub, $feed, $post, 'https://example.com/cancel/alice');

    $auth->shouldReceive('hash')->andReturn('token-for-alice');

    $emailTemplateFactory
        ->shouldReceive('createNewsletter')
        ->with($sub, $feed, $post, 'token-for-alice')
        ->once()
        ->andReturn($template);

    $sender->shouldReceive('send')->with($template)->once();

    $newsletter = new Newsletter($sender, $emailTemplateFactory, $auth);
    $newsletter->sendPostToSubscribers($feed, $post, $sub);
});
