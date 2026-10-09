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
    expect($body)->toContain('href="https://example.com/post?utm_source=simple-newsletter.com&amp;utm_medium=email"');
    expect($body)->toContain('https://example.com/cancel');
    expect($body)->toContain('<p>content html</p>');
});

test('Newsletter appends utm with ampersand when post uri already has query string', function (): void {
    $subscription = new Subscription('https://example.com/feed', 'user@example.com');
    $feed = new Feed(
        new FeedMetadata('https://example.com/feed', 'Blog Title', 'https://example.com', new \DateTimeImmutable()),
    );
    $post = new Post('https://example.com/post?id=1', 'Post Title', '<p>content html</p>');
    $newsletter = new Newsletter($subscription, $feed, [$post], 'https://example.com/cancel');

    expect($newsletter->body())->toContain('href="https://example.com/post?id=1&amp;utm_source=simple-newsletter.com&amp;utm_medium=email"');
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

test('Newsletter body HTML-encodes hostile post titles and links', function (): void {
    $subscription = new Subscription('https://example.com/feed', 'user@example.com');
    $feed = new Feed(
        new FeedMetadata('https://example.com/feed', 'Blog </a><script>alert(1)</script>', 'https://evil.example/home" onmouseover="alert(1)" href="', new \DateTimeImmutable()),
    );
    $post = new Post(
        'https://evil.example/post" onmouseover="alert(1)" href="',
        'Post " onmouseover="alert(2)"><script>alert(3)</script>',
        '<p>content html</p>',
    );
    $newsletter = new Newsletter($subscription, $feed, [$post], 'https://example.com/cancel');

    $body = $newsletter->body();

    expect($body)
        ->and($body)->toContain('&quot; onmouseover=&quot;alert(1)&quot; href=&quot;')
        ->and($body)->toContain('Post &quot; onmouseover=&quot;alert(2)&quot;&gt;&lt;script&gt;alert(3)&lt;/script&gt;')
        ->and($body)->not->toContain('<script>alert(3)</script>')
        ->and($body)->toContain('<p>content html</p>');
});


