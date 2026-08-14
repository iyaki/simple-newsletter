<?php

declare(strict_types=1);

use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\FeedMetadata;
use SimpleNewsletter\Data\Post;
use SimpleNewsletter\Data\Subscription;
use SimpleNewsletter\Templates\Email\Newsletter;

test('Newsletter recipient returns subscription email', function (): void {
    $subscription = new Subscription('https://example.com/feed', 'user@example.com');
    $feed = new Feed(
        new FeedMetadata('https://example.com/feed', 'Blog Title', 'https://example.com', new \DateTimeImmutable()),
    );
    $post = new Post('https://example.com/post', 'Post Title', '<p>content html</p>');
    $newsletter = new Newsletter($subscription, $feed, [$post], 'https://example.com/cancel');

    expect($newsletter->recipient())->toBe('user@example.com');
});

test('Newsletter subject combines post title and feed title', function (): void {
    $subscription = new Subscription('https://example.com/feed', 'user@example.com');
    $feed = new Feed(
        new FeedMetadata('https://example.com/feed', 'Blog Title', 'https://example.com', new \DateTimeImmutable()),
    );
    $post = new Post('https://example.com/post', 'Post Title', '<p>content html</p>');
    $newsletter = new Newsletter($subscription, $feed, [$post], 'https://example.com/cancel');

    expect($newsletter->subject())->toBe('Post Title - Blog Title');
});

test('Newsletter body contains post uri and cancellation uri', function (): void {
    $subscription = new Subscription('https://example.com/feed', 'user@example.com');
    $feed = new Feed(
        new FeedMetadata('https://example.com/feed', 'Blog Title', 'https://example.com', new \DateTimeImmutable()),
    );
    $post = new Post('https://example.com/post', 'Post Title', '<p>content html</p>');
    $newsletter = new Newsletter($subscription, $feed, [$post], 'https://example.com/cancel');

    $body = $newsletter->body();
    expect($body)->toContain('https://example.com/post');
    expect($body)->toContain('https://example.com/cancel');
    expect($body)->toContain('<p>content html</p>');
});

test('Newsletter renders multiple posts separated and with digest subject', function (): void {
    $subscription = new Subscription('https://example.com/feed', 'user@example.com');
    $feed = new Feed(
        new FeedMetadata('https://example.com/feed', 'Blog Title', 'https://example.com', new \DateTimeImmutable()),
    );
    $newest = new Post('https://example.com/newest', 'Newest Title', '<p>newest</p>');
    $older = new Post('https://example.com/older', 'Older Title', '<p>older</p>');
    $newsletter = new Newsletter($subscription, $feed, [$newest, $older], 'https://example.com/cancel');

    expect($newsletter->subject())->toBe('Newest Title (+1 more) - Blog Title');

    $body = $newsletter->body();
    expect($body)->toContain('Newest Title');
    expect($body)->toContain('https://example.com/newest');
    expect($body)->toContain('<p>newest</p>');
    expect($body)->toContain('Older Title');
    expect($body)->toContain('https://example.com/older');
    expect($body)->toContain('<p>older</p>');
    expect($body)->toContain('<hr');
    expect($body)->toContain('https://example.com/cancel');
});


