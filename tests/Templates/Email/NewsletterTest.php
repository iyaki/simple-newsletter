<?php

declare(strict_types=1);

use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\Post;
use SimpleNewsletter\Data\Subscription;
use SimpleNewsletter\Templates\Email\Newsletter;

test('Newsletter recipient returns subscription email', function () {
    $subscription = new Subscription('https://example.com/feed', 'user@example.com');
    $feed = new Feed('https://example.com/feed', 'Blog Title', 'https://example.com', new \DateTimeImmutable());
    $post = new Post('https://example.com/post', 'Post Title', '<p>content html</p>');
    $newsletter = new Newsletter($subscription, $feed, $post, 'https://example.com/cancel');

    expect($newsletter->recipient())->toBe('user@example.com');
});

test('Newsletter subject combines post title and feed title', function () {
    $subscription = new Subscription('https://example.com/feed', 'user@example.com');
    $feed = new Feed('https://example.com/feed', 'Blog Title', 'https://example.com', new \DateTimeImmutable());
    $post = new Post('https://example.com/post', 'Post Title', '<p>content html</p>');
    $newsletter = new Newsletter($subscription, $feed, $post, 'https://example.com/cancel');

    expect($newsletter->subject())->toBe('Post Title - Blog Title');
});

test('Newsletter body contains post uri and cancellation uri', function () {
    $subscription = new Subscription('https://example.com/feed', 'user@example.com');
    $feed = new Feed('https://example.com/feed', 'Blog Title', 'https://example.com', new \DateTimeImmutable());
    $post = new Post('https://example.com/post', 'Post Title', '<p>content html</p>');
    $newsletter = new Newsletter($subscription, $feed, $post, 'https://example.com/cancel');

    $body = $newsletter->body();
    expect($body)->toContain('https://example.com/post');
    expect($body)->toContain('https://example.com/cancel');
    expect($body)->toContain('<p>content html</p>');
});
