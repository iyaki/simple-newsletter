<?php

declare(strict_types=1);

use SimpleNewsletter\Components\Auth;
use SimpleNewsletter\Components\EmailTemplateFactory;
use SimpleNewsletter\Components\Sender;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\FeedMetadata;
use SimpleNewsletter\Data\Post;
use SimpleNewsletter\Data\Subscription;
use SimpleNewsletter\Models\Newsletter;
use SimpleNewsletter\Templates\Email\SubscriptionConfirmation;

test('sendConfirmation calls sender with template from EmailTemplateFactory', function () {
    $sender = $this->createMock(Sender::class);
    $emailTemplateFactory = $this->createMock(EmailTemplateFactory::class);
    $auth = $this->createMock(Auth::class);

    $now = new DateTimeImmutable();
    $feed = new Feed(new FeedMetadata('https://example.com/feed', 'Test Feed', 'https://example.com', $now));
    $subscription = new Subscription('https://example.com/feed', 'user@example.com');

    $token = 'generated-token';
    $template = new SubscriptionConfirmation(
        'user@example.com',
        $feed,
        'https://example.com/confirm?token=generated-token',
    );

    $auth->expects($this->once())->method('hash')->with('user@example.com')->willReturn($token);

    $emailTemplateFactory
        ->expects($this->once())
        ->method('createConfirmation')
        ->with($subscription, $feed, $token)
        ->willReturn($template);

    $sender->expects($this->once())->method('send')->with($template);

    $newsletter = new Newsletter($sender, $emailTemplateFactory, $auth);
    $newsletter->sendConfirmation($feed, $subscription);
});

test('sendConfirmation uses auth hash of subscription email as token', function () {
    $sender = $this->createMock(Sender::class);
    $emailTemplateFactory = $this->createMock(EmailTemplateFactory::class);
    $auth = $this->createMock(Auth::class);

    $now = new DateTimeImmutable();
    $feed = new Feed(new FeedMetadata('https://example.com/feed', 'Test Feed', 'https://example.com', $now));
    $subscription = new Subscription('https://example.com/feed', 'user@example.com');

    $expectedToken = 'hash-of-email';

    $auth->expects($this->once())->method('hash')->with('user@example.com')->willReturn($expectedToken);

    $emailTemplateFactory
        ->expects($this->once())
        ->method('createConfirmation')
        ->with($this->anything(), $this->anything(), $expectedToken)
        ->willReturn(new SubscriptionConfirmation('user@example.com', $feed, 'https://example.com/confirm'));

    $newsletter = new Newsletter($sender, $emailTemplateFactory, $auth);
    $newsletter->sendConfirmation($feed, $subscription);
});

test('sendPostToSubscribers calls sender for each subscription', function () {
    $sender = $this->createMock(Sender::class);
    $emailTemplateFactory = $this->createMock(EmailTemplateFactory::class);
    $auth = $this->createMock(Auth::class);

    $now = new DateTimeImmutable();
    $feed = new Feed(new FeedMetadata('https://example.com/feed', 'Test Feed', 'https://example.com', $now));
    $post = new Post('https://example.com/post1', 'Post 1', 'Content 1');

    $sub1 = new Subscription('https://example.com/feed', 'user1@example.com');
    $sub2 = new Subscription('https://example.com/feed', 'user2@example.com');

    $template1 = new SimpleNewsletter\Templates\Email\Newsletter(
        $sub1,
        $feed,
        $post,
        'https://example.com/cancel/user1',
    );
    $template2 = new SimpleNewsletter\Templates\Email\Newsletter(
        $sub2,
        $feed,
        $post,
        'https://example.com/cancel/user2',
    );

    $auth
        ->expects($this->exactly(2))
        ->method('hash')
        ->willReturnMap([
            ['user1@example.com', 'token1'],
            ['user2@example.com', 'token2'],
        ]);

    $emailTemplateFactory
        ->expects($this->exactly(2))
        ->method('createNewsletter')
        ->willReturnMap([
            [$sub1, $feed, $post, 'token1', $template1],
            [$sub2, $feed, $post, 'token2', $template2],
        ]);

    $invocations = [];
    $sender
        ->expects($this->exactly(2))
        ->method('send')
        ->willReturnCallback(function ($template) use (&$invocations): void {
            $invocations[] = $template;
        });
    $newsletter = new Newsletter($sender, $emailTemplateFactory, $auth);
    $newsletter->sendPostToSubscribers($feed, $post, $sub1, $sub2);

    expect($invocations)->toHaveCount(2);
    expect($invocations[0])->toBe($template1);
    expect($invocations[1])->toBe($template2);
});

test('sendPostToSubscribers creates correct template per subscription', function () {
    $sender = $this->createMock(Sender::class);
    $emailTemplateFactory = $this->createMock(EmailTemplateFactory::class);
    $auth = $this->createMock(Auth::class);

    $now = new DateTimeImmutable();
    $feed = new Feed(new FeedMetadata('https://example.com/feed', 'Test Feed', 'https://example.com', $now));
    $post = new Post('https://example.com/post1', 'Post 1', 'Content 1');

    $sub = new Subscription('https://example.com/feed', 'alice@example.com');

    $template = new SimpleNewsletter\Templates\Email\Newsletter($sub, $feed, $post, 'https://example.com/cancel/alice');

    $auth->method('hash')->willReturn('token-for-alice');

    $emailTemplateFactory
        ->expects($this->once())
        ->method('createNewsletter')
        ->with($sub, $feed, $post, 'token-for-alice')
        ->willReturn($template);

    $sender->expects($this->once())->method('send')->with($template);

    $newsletter = new Newsletter($sender, $emailTemplateFactory, $auth);
    $newsletter->sendPostToSubscribers($feed, $post, $sub);
});
