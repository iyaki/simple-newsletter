<?php

declare(strict_types=1);

namespace Tests\Adapters;

use Laminas\Feed\Reader\Entry\EntryInterface;
use Laminas\Feed\Reader\Feed\FeedInterface;
use SimpleNewsletter\Adapters\FeedImporterLaminas;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\FeedMetadata;
use Tests\Adapters\FeedTestServer;

const FEED_TEST_PORT = 9995;
const FEED_TEST_BASE = 'http://127.0.0.1:' . FEED_TEST_PORT;

beforeAll(function (): void {
    FeedTestServer::start();
});

afterAll(function (): void {
    FeedTestServer::stop();
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


