<?php

declare(strict_types=1);

use Laminas\Feed\Reader\Entry\EntryInterface;
use Laminas\Feed\Reader\Feed\FeedInterface;
use SimpleNewsletter\Adapters\FeedImporterLaminas;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\Post;

const FEED_TEST_PORT = 9995;

const FEED_TEST_BASE = 'http://127.0.0.1:' . FEED_TEST_PORT;

beforeAll(function (): void {
    $feedDir = '/tmp/feedtest';
    if (! \is_dir($feedDir)) {
        \mkdir($feedDir, 0o777, true);
        \file_put_contents($feedDir . '/valid.xml', <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
            <channel>
            <title>Test Blog</title>
            <link>https://example.com</link>
            <item>
            <title>First Post</title>
            <link>https://example.com/post1</link>
            </item>
            </channel>
            </rss>
            XML);
        \file_put_contents($feedDir . '/invalid.txt', 'not xml');
    }

    $cmd = \sprintf('php -S 0.0.0.0:%d -t %s >/dev/null 2>&1', FEED_TEST_PORT, \escapeshellarg($feedDir));
    $proc = @\proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (! \is_resource($proc)) {
        throw new \RuntimeException('Failed to start PHP test server');
    }
    \fclose($pipes[0]);
    \fclose($pipes[1]);
    \fclose($pipes[2]);

    for ($i = 0; $i < 15; $i++) {
        $sock = @\fsockopen('127.0.0.1', FEED_TEST_PORT, $e, $s, 1);
        if (\is_resource($sock)) {
            \fclose($sock);
            break;
        }
        \usleep(100_000);
    }

    $GLOBALS['_feed_server'] = $proc;
});

afterAll(function (): void {
    if (isset($GLOBALS['_feed_server']) && \is_resource($GLOBALS['_feed_server'])) {
        @\proc_terminate($GLOBALS['_feed_server']);
        @\proc_close($GLOBALS['_feed_server']);
    }
});

// ─── Production import tests (real HTTP server) ───────────────────────────

test('fetchNew uses real import for valid feed', function () {
    $importer = new FeedImporterLaminas();
    $feed = $importer->fetchNew(FEED_TEST_BASE . '/valid.xml');

    expect($feed->title)->toEqual('Test Blog');
    expect($feed->link)->toEqual('https://example.com');
});

test('fetchWithPosts uses real import for valid feed', function () {
    $importer = new FeedImporterLaminas();
    $feed = new Feed(FEED_TEST_BASE . '/valid.xml', '', '', new \DateTimeImmutable());
    $result = $importer->fetchWithPosts($feed);

    $posts = \iterator_to_array($result->posts);
    expect($posts)->toHaveCount(1);
    expect($posts[0]?->title)->toEqual('First Post');
});

test('fetchNew wraps invalid feed content in EndUserException', function () {
    $importer = new FeedImporterLaminas();
    expect(fn () => $importer->fetchNew(FEED_TEST_BASE . '/invalid.txt'))->toThrow(EndUserException::class);
});

// ─── Test double tests (mock import) ──────────────────────────────────────

class TestFeedImporter extends FeedImporterLaminas
{
    private ?FeedInterface $mockFeed = null;

    public function setMockFeed(FeedInterface $feed): void
    {
        $this->mockFeed = $feed;
    }

    protected function import(string $uri): FeedInterface
    {
        $feed = $this->mockFeed;
        \assert($feed !== null, 'Mock feed must be set before calling import');

        return $feed;
    }
}

/**
 * Helper: configure a mock FeedInterface to yield the given entries on iteration.
 */
function configureFeedIterator(FeedInterface $mock, array &$entries): void
{
    $mock->method('rewind')->willReturnCallback(function () use (&$entries): void {
        \reset($entries);
    });
    $mock->method('valid')->willReturnCallback(function () use (&$entries): bool {
        return \current($entries) !== false;
    });
    $mock->method('current')->willReturnCallback(function () use (&$entries): EntryInterface|false {
        return \current($entries);
    });
    $mock->method('next')->willReturnCallback(function () use (&$entries): void {
        \next($entries);
    });
}

test('fetchNew creates Feed with correct URI, title and link (mock)', function () {
    $sourceFeed = $this->createMock(FeedInterface::class);
    $sourceFeed->method('getTitle')->willReturn('Test Feed Title');
    $sourceFeed->method('getLink')->willReturn('https://example.com');

    $importer = new TestFeedImporter();
    $importer->setMockFeed($sourceFeed);

    $feed = $importer->fetchNew('https://example.com/feed');

    expect($feed->uri)->toEqual('https://example.com/feed');
    expect($feed->title)->toEqual('Test Feed Title');
    expect($feed->link)->toEqual('https://example.com');
});

test('fetch preserves lastSentPostUri from input Feed (mock)', function () {
    $sourceFeed = $this->createMock(FeedInterface::class);
    $sourceFeed->method('getTitle')->willReturn('Updated Title');
    $sourceFeed->method('getLink')->willReturn('https://example.com');

    $inputFeed = new Feed(
        'https://example.com/feed',
        'Old Title',
        'https://example.com',
        new \DateTimeImmutable(),
        'https://example.com/last-post',
    );

    $importer = new TestFeedImporter();
    $importer->setMockFeed($sourceFeed);

    $feed = $importer->fetch($inputFeed);

    expect($feed->lastSentPostUri)->toEqual('https://example.com/last-post');
    expect($feed->uri)->toEqual('https://example.com/feed');
    expect($feed->title)->toEqual('Updated Title');
});

test('fetchWithPosts yields Post objects with sanitized content (mock)', function () {
    $entry = $this->createMock(EntryInterface::class);
    $entry->method('getPermalink')->willReturn('https://example.com/post1');
    $entry->method('getLink')->willReturn('https://example.com/post1');
    $entry->method('getTitle')->willReturn('Post One');
    $entry->method('getContent')->willReturn('<p>Hello</p><script>alert("xss")</script>');

    $entries = [$entry];
    $sourceFeed = $this->createMock(FeedInterface::class);
    $sourceFeed->method('getTitle')->willReturn('Test Feed');
    $sourceFeed->method('getLink')->willReturn('https://example.com');
    configureFeedIterator($sourceFeed, $entries);

    $inputFeed = new Feed('https://example.com/feed', 'Test Feed', 'https://example.com', new \DateTimeImmutable());

    $importer = new TestFeedImporter();
    $importer->setMockFeed($sourceFeed);

    $resultFeed = $importer->fetchWithPosts($inputFeed);
    $posts = \iterator_to_array($resultFeed->posts);

    expect($posts)->toHaveCount(1);
    expect($posts[0])->toBeInstanceOf(Post::class);
    expect($posts[0]?->uri)->toEqual('https://example.com/post1');
    expect($posts[0]?->title)->toEqual('Post One');
    expect($posts[0]?->content)->not->toContain('<script');
    expect($posts[0]->content)->toContain('<p>Hello</p>');
});

test('fetchWithPosts falls back to getLink when getPermalink returns null (mock)', function () {
    $entry = $this->createMock(EntryInterface::class);
    $entry->method('getPermalink')->willReturn(null);
    $entry->method('getLink')->willReturn('https://example.com/fallback-link');
    $entry->method('getTitle')->willReturn('Fallback Post');
    $entry->method('getContent')->willReturn('<p>content</p>');

    $entries = [$entry];
    $sourceFeed = $this->createMock(FeedInterface::class);
    $sourceFeed->method('getTitle')->willReturn('Test Feed');
    $sourceFeed->method('getLink')->willReturn('https://example.com');
    configureFeedIterator($sourceFeed, $entries);

    $inputFeed = new Feed('https://example.com/feed', 'Test Feed', 'https://example.com', new \DateTimeImmutable());

    $importer = new TestFeedImporter();
    $importer->setMockFeed($sourceFeed);

    $resultFeed = $importer->fetchWithPosts($inputFeed);
    $posts = \iterator_to_array($resultFeed->posts);

    expect($posts)->toHaveCount(1);
    expect($posts[0]?->uri)->toEqual('https://example.com/fallback-link');
});
