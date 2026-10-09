<?php

declare(strict_types=1);

namespace Tests\Adapters;

use SimpleNewsletter\Adapters\FeedImporterLaminas;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\FeedMetadata;

const FEED_TEST_PORT = 9995;
const FEED_TEST_BASE = 'http://127.0.0.1:' . FEED_TEST_PORT;

beforeAll(function (): void {
    // The loopback fixture is not publicly routable; lift the egress policy for tests.
    \putenv(\SimpleNewsletter\Adapters\PrivateAddressGuard::ALLOW_ENV . '=1');
    FeedTestServer::start();
});

afterAll(function (): void {
    FeedTestServer::stop();
    \putenv(\SimpleNewsletter\Adapters\PrivateAddressGuard::ALLOW_ENV);
});

test('fetchNew uses real import for valid feed', function (): void {
    $importer = new FeedImporterLaminas();
    $feed = $importer->fetchNew(FEED_TEST_BASE . '/valid.xml');
    expect($feed)->toBeInstanceOf(Feed::class);
})->group('integration');

test('fetchWithPosts uses real import for valid feed', function (): void {
    $importer = new FeedImporterLaminas();
    $metadata = new FeedMetadata(FEED_TEST_BASE . '/valid.xml', 'Test', 'test', new \DateTimeImmutable());
    $inputFeed = new Feed($metadata);
    $feed = $importer->fetchWithPosts($inputFeed);
    expect($feed)->toBeInstanceOf(Feed::class);
    expect($feed->posts)->not->toBeEmpty();
})->group('integration');

test('fetchNew wraps invalid feed content in EndUserException', function (): void {
    $importer = new FeedImporterLaminas();
    expect(fn () => $importer->fetchNew(FEED_TEST_BASE . '/invalid.txt'))
        ->toThrow(EndUserException::class);
})->group('integration');

test('fetchWithPosts drops entries whose permalink scheme is not http(s)', function (): void {
    $importer = new FeedImporterLaminas();
    $metadata = new FeedMetadata(FEED_TEST_BASE . '/hostile.xml', 'Test', 'test', new \DateTimeImmutable());

    $feed = $importer->fetchWithPosts(new Feed($metadata));
    \assert(\is_array($feed->posts), 'posts should be an array');

    $uris = \array_map(static fn (\SimpleNewsletter\Data\Post $post): string => $post->uri, $feed->posts);
    expect($uris)->toBe(['https://example.com/safe-post']);
})->group('integration');

test('feed-declared link with an unsafe scheme falls back to the feed URI', function (): void {
    $importer = new FeedImporterLaminas();

    $feed = $importer->fetchNew(FEED_TEST_BASE . '/hostile.xml');

    expect($feed->getLink())->toBe(FEED_TEST_BASE . '/hostile.xml');
})->group('integration');


