<?php

declare(strict_types=1);

use SimpleNewsletter\Components\EmailTemplateFactory;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\FeedMetadata;
use SimpleNewsletter\Data\Post;
use SimpleNewsletter\Data\Subscription;

test('createConfirmation returns SubscriptionConfirmation with correct recipient and confirmation URI contains token', function (): void {
    $factory = new EmailTemplateFactory('https://example.com');
    $metadata = new FeedMetadata(
        'https://example.com/feed',
        'Test Feed',
        'https://example.com',
        new \DateTimeImmutable(),
    );
    $feed = new Feed($metadata);
    $subscription = new Subscription('https://example.com/feed', 'user@example.com');
    $token = 'test-token-123';

    $result = $factory->createConfirmation($subscription, $feed, $token);

    expect($result)->toBeInstanceOf(\SimpleNewsletter\Templates\Email\SubscriptionConfirmation::class);
    expect($result->recipient())->toEqual('user@example.com');
    expect($result->body())->toContain(\urlencode($token));
});

test('createNewsletter returns Newsletter with correct subject format and cancellation URI contains token', function (): void {
    $factory = new EmailTemplateFactory('https://example.com');
    $metadata = new FeedMetadata(
        'https://example.com/feed',
        'Test Feed',
        'https://example.com',
        new \DateTimeImmutable(),
    );
    $feed = new Feed($metadata);
    $subscription = new Subscription('https://example.com/feed', 'user@example.com');
    $post = new Post('https://example.com/post/1', 'Test Post Title', '<p>Test content</p>');
    $token = 'cancel-token-456';

    $result = $factory->createNewsletter($subscription, $feed, $post, $token);

    expect($result)->toBeInstanceOf(\SimpleNewsletter\Templates\Email\Newsletter::class);
    expect($result->recipient())->toEqual('user@example.com');
    expect($result->subject())->toEqual('Test Post Title - Test Feed');
    expect($result->body())->toContain(\urlencode($token));
});

covers(SimpleNewsletter\Components\EmailTemplateFactory::class);

