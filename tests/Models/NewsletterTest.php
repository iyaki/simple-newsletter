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

    $auth->shouldReceive('hash')->twice()->andReturn('token1', 'token2');

    $emailTemplateFactory
        ->shouldReceive('createNewsletter')
        ->with($sub1, $feed, $post, 'token1')
        ->once()
        ->andReturn($template1);
    $emailTemplateFactory
        ->shouldReceive('createNewsletter')
        ->with($sub2, $feed, $post, 'token2')
        ->once()
        ->andReturn($template2);

    $sender->shouldReceive('send')->with($template1)->once();
    $sender->shouldReceive('send')->with($template2)->once();

    $newsletter = new Newsletter($sender, $emailTemplateFactory, $auth);
    $newsletter->sendPostToSubscribers($feed, $post, $sub1, $sub2);
});
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

covers(SimpleNewsletter\Models\Newsletter::class);

