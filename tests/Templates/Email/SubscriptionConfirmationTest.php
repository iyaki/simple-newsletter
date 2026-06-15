<?php

declare(strict_types=1);

use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\FeedMetadata;
use SimpleNewsletter\Templates\Email\SubscriptionConfirmation;

test('SubscriptionConfirmation recipient returns the email string', function (): void {
    $metadata = new FeedMetadata(
        'https://example.com/feed',
        'My Feed',
        'https://example.com',
        new \DateTimeImmutable(),
    );
    $feed = new Feed($metadata);
    $confirmation = new SubscriptionConfirmation('user@example.com', $feed, 'https://example.com/confirm');

    expect($confirmation->recipient())->toBe('user@example.com');
});

test('SubscriptionConfirmation subject is fixed', function (): void {
    $metadata = new FeedMetadata(
        'https://example.com/feed',
        'My Feed',
        'https://example.com',
        new \DateTimeImmutable(),
    );
    $feed = new Feed($metadata);
    $confirmation = new SubscriptionConfirmation('user@example.com', $feed, 'https://example.com/confirm');

    expect($confirmation->subject())->toBe('Newsletter subscription confirmation');
});

test('SubscriptionConfirmation body contains confirmation uri, feed title and feed link', function (): void {
    $metadata = new FeedMetadata(
        'https://example.com/feed',
        'My Feed',
        'https://example.com',
        new \DateTimeImmutable(),
    );
    $feed = new Feed($metadata);
    $confirmation = new SubscriptionConfirmation('user@example.com', $feed, 'https://example.com/confirm');

    $body = $confirmation->body();
    expect($body)->toContain('https://example.com/confirm');
    expect($body)->toContain('My Feed');
    expect($body)->toContain('https://example.com');
});
