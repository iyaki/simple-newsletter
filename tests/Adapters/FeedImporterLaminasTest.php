<?php

declare(strict_types=1);

/**
 * @throws \Symfony\Component\Process\Exception\LogicException
 * @throws \Symfony\Component\Process\Exception\RuntimeException
 * @throws \Symfony\Component\Process\Exception\ProcessStartFailedException
 */

use Laminas\Feed\Reader\Entry\EntryInterface;
use Laminas\Feed\Reader\Feed\FeedInterface;
use SimpleNewsletter\Adapters\FeedImporterLaminas;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\Post;
use Tests\Adapters\FeedTestServer;
use Tests\Adapters\TestFeedImporter;

const FEED_TEST_PORT = 9995;

const FEED_TEST_BASE = 'http://127.0.0.1:' . FEED_TEST_PORT;

beforeAll(function (): void {
    FeedTestServer::start();
});
/**
 * @throws \Symfony\Component\Process\Exception\LogicException
 * @throws \Symfony\Component\Process\Exception\RuntimeException
 * @throws \Symfony\Component\Process\Exception\ProcessStartFailedException
 */

afterAll(function (): void {
    FeedTestServer::stop();
});

// ─── Production import tests (real HTTP server) ───────────────────────────

test('fetchNew uses real import for valid feed', function (): void {
    $importer = new FeedImporterLaminas();
    $feed = $importer->fetchNew(FEED_TEST_BASE . '/valid.xml');

    expect($feed->getTitle())->toEqual('Test Blog');
    expect($feed->getLink())->toEqual('https://example.com');
});

test('fetchWithPosts uses real import for valid feed', function (): void {
    $importer = new FeedImporterLaminas();
    $metadata = new \SimpleNewsletter\Data\FeedMetadata(
        FEED_TEST_BASE . '/valid.xml',
        '',
        'https://example.com',
        new \DateTimeImmutable(),
    );
    $feed = new Feed($metadata, null, []);
    $result = $importer->fetchWithPosts($feed);

    $posts = \iterator_to_array($result->posts);
    expect($posts)->toHaveCount(1);
    \assert(isset($posts[0]));
    expect($posts[0]->title)->toEqual('First Post');
});

test('fetchNew wraps invalid feed content in EndUserException', function (): void {
    $importer = new FeedImporterLaminas();
    expect(static function () use ($importer): void {
        $importer->fetchNew(FEED_TEST_BASE . '/invalid.txt');
    })->toThrow(EndUserException::class);
});

// ─── Test double tests (mock import) ──────────────────────────────────────

/**
 * Helper: configure a mock FeedInterface to yield the given entries on iteration.
 *
 * @param FeedInterface<EntryInterface>&\Mockery\MockInterface $mock
 * @param list<EntryInterface> $entries
 * @throws \Random\RandomException
 */
function configure_feed_iterator(FeedInterface $mock, array $entries): void
{
    $mock->shouldReceive('rewind')->andReturnUsing(function () use (&$entries): void {
        \reset($entries);
    });
    $mock->shouldReceive('valid')->andReturnUsing(function () use (&$entries): bool {
        return \current($entries) !== false;
    });
    $mock->shouldReceive('current')->andReturnUsing(function () use (&$entries): EntryInterface|false {
        return \current($entries);
    });
    $mock->shouldReceive('next')->andReturnUsing(function () use (&$entries): void {
        \next($entries);
    });
}

test('fetchNew creates Feed with correct URI, title and link (mock)', function (): void {
    $sourceFeed = \Mockery::mock(FeedInterface::class);
    $sourceFeed->shouldReceive('getTitle')->andReturn('Test Feed Title');
    $sourceFeed->shouldReceive('getLink')->andReturn('https://example.com');

    $importer = new TestFeedImporter();
    /** @phpstan-var FeedInterface<EntryInterface> $mockFeed */
    $mockFeed = $sourceFeed;
    $importer->setMockFeed($mockFeed);

    $metadata = new \SimpleNewsletter\Data\FeedMetadata(
        'https://example.com/feed',
        'Test Feed Title',
        'https://example.com',
        new \DateTimeImmutable(),
    );
    $feed = $importer->fetchNew('https://example.com/feed');

    expect($feed->getUri())->toEqual('https://example.com/feed');
    expect($feed->getTitle())->toEqual('Test Feed Title');
    expect($feed->getLink())->toEqual('https://example.com');
});

test('fetch preserves lastSentPostUri from input Feed (mock)', function (): void {
    $sourceFeed = \Mockery::mock(FeedInterface::class);
    $sourceFeed->shouldReceive('getTitle')->andReturn('Updated Title');
    $sourceFeed->shouldReceive('getLink')->andReturn('https://example.com');

    $metadata = new \SimpleNewsletter\Data\FeedMetadata(
        'https://example.com/feed',
        'Old Title',
        'https://example.com',
        new \DateTimeImmutable(),
    );
    $inputFeed = new Feed($metadata, 'https://example.com/last-post', []);

    $importer = new TestFeedImporter();
    /** @phpstan-var FeedInterface<EntryInterface> $mockFeed */
    $mockFeed = $sourceFeed;
    $importer->setMockFeed($mockFeed);

    $feed = $importer->fetch($inputFeed);

    expect($feed->lastSentPostUri)->toEqual('https://example.com/last-post');
    expect($feed->getUri())->toEqual('https://example.com/feed');
    expect($feed->getTitle())->toEqual('Updated Title');
});

test('fetchWithPosts yields Post objects with sanitized content (mock)', function (): void {
    $entry = \Mockery::mock(EntryInterface::class);
    $entry->shouldReceive('getPermalink')->andReturn('https://example.com/post1');
    $entry->shouldReceive('getLink')->andReturn('https://example.com/post1');
    $entry->shouldReceive('getTitle')->andReturn('Post One');
    $entry->shouldReceive('getContent')->andReturn('<p>Hello</p><script>alert("xss")</script>');

    $entries = [$entry];
    $sourceFeed = \Mockery::mock(FeedInterface::class);
    $sourceFeed->shouldReceive('getTitle')->andReturn('Test Feed');
    $sourceFeed->shouldReceive('getLink')->andReturn('https://example.com');
    /** @var FeedInterface<EntryInterface>&\Mockery\MockInterface $mockFeedForIterator */
    $mockFeedForIterator = $sourceFeed;
    configure_feed_iterator($mockFeedForIterator, $entries);

    $metadata = new \SimpleNewsletter\Data\FeedMetadata(
        'https://example.com/feed',
        'Test Feed',
        'https://example.com',
        new \DateTimeImmutable(),
    );
    $inputFeed = new Feed($metadata, null, []);

    $importer = new TestFeedImporter();
    /** @phpstan-var FeedInterface<EntryInterface> $mockFeed */
    $mockFeed = $sourceFeed;
    $importer->setMockFeed($mockFeed);

    $resultFeed = $importer->fetchWithPosts($inputFeed);
    $posts = \iterator_to_array($resultFeed->posts);

    expect($posts)->toHaveCount(1);
    \assert(isset($posts[0]));
    expect($posts[0])->toBeInstanceOf(Post::class);
    expect($posts[0]->uri)->toEqual('https://example.com/post1');
    expect($posts[0]->title)->toEqual('Post One');
    expect($posts[0]->content)->not->toContain('<script');
    expect($posts[0]->content)->toContain('<p>Hello</p>');
});

test('fetchWithPosts falls back to getLink when getPermalink returns null (mock)', function (): void {
    $entry = \Mockery::mock(EntryInterface::class);
    $entry->shouldReceive('getLink')->andReturn('https://example.com/fallback-link');
    $entry->shouldReceive('getTitle')->andReturn('Fallback Post');
    $entry->shouldReceive('getContent')->andReturn('<p>content</p>');

    $entries = [$entry];
    $sourceFeed = \Mockery::mock(FeedInterface::class);
    $sourceFeed->shouldReceive('getTitle')->andReturn('Test Feed');
    $sourceFeed->shouldReceive('getLink')->andReturn('https://example.com');
    /** @var FeedInterface<EntryInterface>&\Mockery\MockInterface $mockFeedForIterator */
    $mockFeedForIterator = $sourceFeed;
    configure_feed_iterator($mockFeedForIterator, $entries);

    $metadata = new \SimpleNewsletter\Data\FeedMetadata(
        'https://example.com/feed',
        'Test Feed',
        'https://example.com',
        new \DateTimeImmutable(),
    );
    $inputFeed = new Feed($metadata, null, []);

    $importer = new TestFeedImporter();
    /** @phpstan-var FeedInterface<EntryInterface> $mockFeed */
    $mockFeed = $sourceFeed;
    $importer->setMockFeed($mockFeed);

    $resultFeed = $importer->fetchWithPosts($inputFeed);
    $posts = \iterator_to_array($resultFeed->posts);

    expect($posts)->toHaveCount(1);
    \assert(isset($posts[0]));
    expect($posts[0]->uri)->toEqual('https://example.com/fallback-link');
});
