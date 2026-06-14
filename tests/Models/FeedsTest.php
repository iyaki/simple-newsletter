<?php

declare(strict_types=1);

use SimpleNewsletter\Components\FeedImporter;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\FeedsDAO;
use SimpleNewsletter\Data\Post;
use SimpleNewsletter\Models\Feeds;

test('retrieve returns cached feed when less than 1 day old', function () {
    $feedsDAO = $this->createMock(FeedsDAO::class);
    $feedImporter = $this->createMock(FeedImporter::class);

    $now = new DateTimeImmutable();
    $feed = new Feed('https://example.com/feed', 'Test Feed', 'https://example.com', $now);

    $feedsDAO->expects($this->once())->method('find')->with('https://example.com/feed')->willReturn($feed);

    $feedImporter->expects($this->never())->method('fetch');
    $feedImporter->expects($this->never())->method('fetchNew');
    $feedsDAO->expects($this->never())->method('update');
    $feedsDAO->expects($this->never())->method('new');

    $feeds = new Feeds($feedsDAO, $feedImporter);
    $result = $feeds->retrieve('https://example.com/feed');

    expect($result)->toBe($feed);
});

test('retrieve fetches and updates feed when more than 1 day old', function () {
    $feedsDAO = $this->createMock(FeedsDAO::class);
    $feedImporter = $this->createMock(FeedImporter::class);

    $oldDate = new DateTimeImmutable()->sub(new DateInterval('P2D'));
    $oldFeed = new Feed('https://example.com/feed', 'Old Title', 'https://example.com', $oldDate);

    $now = new DateTimeImmutable();
    $updatedFeed = new Feed('https://example.com/feed', 'New Title', 'https://example.com', $now);

    $feedsDAO->expects($this->once())->method('find')->with('https://example.com/feed')->willReturn($oldFeed);

    $feedImporter->expects($this->once())->method('fetch')->with($oldFeed)->willReturn($updatedFeed);

    $feedsDAO->expects($this->once())->method('update')->with($updatedFeed);

    $feeds = new Feeds($feedsDAO, $feedImporter);
    $result = $feeds->retrieve('https://example.com/feed');

    expect($result)->toBe($updatedFeed);
});

test('retrieve creates new feed when not found in DAO', function () {
    $feedsDAO = $this->createMock(FeedsDAO::class);
    $feedImporter = $this->createMock(FeedImporter::class);

    $uri = 'https://example.com/feed';
    $now = new DateTimeImmutable();
    $newFeed = new Feed($uri, 'New Feed', 'https://example.com', $now);

    $feedsDAO->expects($this->once())->method('find')->with($uri)->willReturn(null);

    $feedImporter->expects($this->once())->method('fetchNew')->with($uri)->willReturn($newFeed);

    $feedsDAO->expects($this->once())->method('new')->with($newFeed);

    $feeds = new Feeds($feedsDAO, $feedImporter);
    $result = $feeds->retrieve($uri);

    expect($result)->toBe($newFeed);
});

test('getScheduled delegates to DAO', function () {
    $feedsDAO = $this->createMock(FeedsDAO::class);
    $feedImporter = $this->createMock(FeedImporter::class);

    $datetime = new DateTimeImmutable();
    $expectedFeeds = [
        new Feed('https://example.com/feed1', 'Feed 1', 'https://example.com', $datetime),
        new Feed('https://example.com/feed2', 'Feed 2', 'https://example.com', $datetime),
    ];

    $feedsDAO->expects($this->once())->method('getScheduled')->with($datetime)->willReturn($expectedFeeds);

    $feeds = new Feeds($feedsDAO, $feedImporter);
    $result = $feeds->getScheduled($datetime);

    expect($result)->toBe($expectedFeeds);
    expect($result)->toHaveCount(2);
});

test('retrieveWithPosts delegates to FeedImporter', function () {
    $feedsDAO = $this->createMock(FeedsDAO::class);
    $feedImporter = $this->createMock(FeedImporter::class);

    $now = new DateTimeImmutable();
    $feed = new Feed('https://example.com/feed', 'Test', 'https://example.com', $now);
    $feedWithPosts = new Feed('https://example.com/feed', 'Test', 'https://example.com', $now, null, [
        new Post('https://example.com/post1', 'Post 1', 'Content 1'),
    ]);

    $feedImporter->expects($this->once())->method('fetchWithPosts')->with($feed)->willReturn($feedWithPosts);

    $feeds = new Feeds($feedsDAO, $feedImporter);
    $result = $feeds->retrieveWithPosts($feed);

    expect($result)->toBe($feedWithPosts);
});

test('updateLastSentPost updates DAO with new lastSentPostUri', function () {
    $feedsDAO = $this->createMock(FeedsDAO::class);
    $feedImporter = $this->createMock(FeedImporter::class);

    $now = new DateTimeImmutable();
    $feed = new Feed('https://example.com/feed', 'Test Feed', 'https://example.com', $now);
    $post = new Post('https://example.com/post1', 'Post 1', 'Content 1');

    $expectedUpdated = new Feed(
        'https://example.com/feed',
        'Test Feed',
        'https://example.com',
        $now,
        'https://example.com/post1',
    );

    $feedsDAO
        ->expects($this->once())
        ->method('update')
        ->with($this->callback(
            fn (Feed $f): bool => (
                $f->uri === $expectedUpdated->uri
                && $f->title === $expectedUpdated->title
                && $f->link === $expectedUpdated->link
                && $f->lastUpdate === $expectedUpdated->lastUpdate
                && $f->lastSentPostUri === $expectedUpdated->lastSentPostUri
            ),
        ));

    $feeds = new Feeds($feedsDAO, $feedImporter);
    $feeds->updateLastSentPost($feed, $post);
});
