<?php

declare(strict_types=1);

use SimpleNewsletter\Components\FeedImporter;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\FeedMetadata;
use SimpleNewsletter\Data\FeedsDAO;
use SimpleNewsletter\Data\Post;
use SimpleNewsletter\Models\Feeds;

test(
    'retrieve returns cached feed when less than 1 day old',
    /**
     * @throws Random\RandomException
     * @throws SimpleNewsletter\Components\EndUserException
     */ function (): void {
        $feedsDAO = \Mockery::mock(FeedsDAO::class);
        $feedImporter = \Mockery::mock(FeedImporter::class);

        $now = new DateTimeImmutable();
        $metadata = new FeedMetadata('https://example.com/feed', 'Test Feed', 'https://example.com', $now);
        $feed = new Feed($metadata);

        $feedsDAO->shouldReceive('find')->andReturn($feed);

        $feedImporter->shouldNotReceive('fetch');
        $feedImporter->shouldNotReceive('fetchNew');
        $feedsDAO->shouldNotReceive('update');
        $feedsDAO->shouldNotReceive('new');

        $feeds = new Feeds($feedsDAO, $feedImporter);
        $result = $feeds->retrieve('https://example.com/feed');

        expect($result)->toBe($feed);
        $feedsDAO->shouldHaveReceived('find', ['https://example.com/feed']);
        $feedsDAO->shouldNotHaveReceived('update');
        $feedsDAO->shouldNotHaveReceived('new');
        $feedImporter->shouldNotHaveReceived('fetch');
        $feedImporter->shouldNotHaveReceived('fetchNew');
    },
);

test(
    'retrieve fetches and updates feed when more than 1 day old',
    /**
     * @throws Random\RandomException
     * @throws SimpleNewsletter\Components\EndUserException
     */ function (): void {
        $feedsDAO = \Mockery::mock(FeedsDAO::class);
        $feedImporter = \Mockery::mock(FeedImporter::class);

        $oldDate = new DateTimeImmutable()->sub(new DateInterval('P2D'));
        $oldMetadata = new FeedMetadata('https://example.com/feed', 'Old Title', 'https://example.com', $oldDate);
        $oldFeed = new Feed($oldMetadata);

        $now = new DateTimeImmutable();
        $updatedMetadata = new FeedMetadata('https://example.com/feed', 'New Title', 'https://example.com', $now);
        $updatedFeed = new Feed($updatedMetadata);

        $feedsDAO->shouldReceive('find')->andReturn($oldFeed);

        $feedImporter->shouldReceive('fetch')->andReturn($updatedFeed);

        $feedsDAO->shouldReceive('update');

        $feeds = new Feeds($feedsDAO, $feedImporter);
        $result = $feeds->retrieve('https://example.com/feed');

        expect($result)->toBe($updatedFeed);
        $feedsDAO->shouldHaveReceived('find', ['https://example.com/feed']);
        $feedImporter->shouldHaveReceived('fetch', [$oldFeed]);
        $feedsDAO->shouldHaveReceived('update', [$updatedFeed]);
    },
);

test(
    'retrieve creates new feed when not found in DAO',
    /**
     * @throws Random\RandomException
     * @throws SimpleNewsletter\Components\EndUserException
     */ function (): void {
        $feedsDAO = \Mockery::mock(FeedsDAO::class);
        $feedImporter = \Mockery::mock(FeedImporter::class);

        $uri = 'https://example.com/feed';
        $now = new DateTimeImmutable();
        $metadata = new FeedMetadata($uri, 'New Feed', 'https://example.com', $now);
        $newFeed = new Feed($metadata);

        $feedsDAO->shouldReceive('find')->andReturn(null);

        $feedImporter->shouldReceive('fetchNew')->andReturn($newFeed);

        $feedsDAO->shouldReceive('new');

        $feeds = new Feeds($feedsDAO, $feedImporter);
        $result = $feeds->retrieve($uri);

        expect($result)->toBe($newFeed);
        $feedsDAO->shouldHaveReceived('find', [$uri]);
        $feedImporter->shouldHaveReceived('fetchNew', [$uri]);
        $feedsDAO->shouldHaveReceived('new', [$newFeed]);
    },
);

test(
    'getScheduled delegates to DAO',
    /**
     * @throws SimpleNewsletter\Components\EndUserException
     */ function (): void {
        $feedsDAO = \Mockery::mock(FeedsDAO::class);
        $feedImporter = \Mockery::mock(FeedImporter::class);

        $datetime = new DateTimeImmutable();
        $metadata1 = new FeedMetadata('https://example.com/feed1', 'Feed 1', 'https://example.com', $datetime);
        $metadata2 = new FeedMetadata('https://example.com/feed2', 'Feed 2', 'https://example.com', $datetime);
        $expectedFeeds = [
            new Feed($metadata1),
            new Feed($metadata2),
        ];

        $feedsDAO->shouldReceive('getScheduled')->andReturn($expectedFeeds);

        $feeds = new Feeds($feedsDAO, $feedImporter);
        $result = $feeds->getScheduled($datetime);

        expect($result)->toBe($expectedFeeds);
        expect($result)->toHaveCount(2);
        $feedsDAO->shouldHaveReceived('getScheduled', [$datetime]);
    },
);

test(
    'retrieveWithPosts delegates to FeedImporter',
    /**
     * @throws SimpleNewsletter\Components\EndUserException
     */ function (): void {
        $feedsDAO = \Mockery::mock(FeedsDAO::class);
        $feedImporter = \Mockery::mock(FeedImporter::class);

        $now = new DateTimeImmutable();
        $metadata = new FeedMetadata('https://example.com/feed', 'Test', 'https://example.com', $now);
        $posts = [
            new Post('https://example.com/post1', 'Post 1', 'Content 1'),
        ];
        $feed = new Feed($metadata, null, $posts);
        $feedWithPosts = new Feed($metadata, null, $posts);

        $feedImporter->shouldReceive('fetchWithPosts')->andReturn($feedWithPosts);

        $feeds = new Feeds($feedsDAO, $feedImporter);
        $result = $feeds->retrieveWithPosts($feed);

        expect($result)->toBe($feedWithPosts);
        $feedImporter->shouldHaveReceived('fetchWithPosts', [$feed]);
    },
);

test(
    'updateLastSentPost updates DAO with new lastSentPostUri',
    /**
     * @throws SimpleNewsletter\Components\EndUserException
     */ function (): void {
        $feedsDAO = \Mockery::mock(FeedsDAO::class);
        $feedImporter = \Mockery::mock(FeedImporter::class);

        $now = new DateTimeImmutable();
        $metadata = new FeedMetadata('https://example.com/feed', 'Test Feed', 'https://example.com', $now);
        $feed = new Feed($metadata);
        $post = new Post('https://example.com/post1', 'Post 1', 'Content 1');

        $feedsDAO->shouldReceive('update');

        $feeds = new Feeds($feedsDAO, $feedImporter);
        $feeds->updateLastSentPost($feed, $post);
        $feedsDAO->shouldHaveReceived('update', function (Feed $f): bool {
            return (
                $f->getUri() === 'https://example.com/feed'
                && $f->getTitle() === 'Test Feed'
                && $f->getLink() === 'https://example.com'
                && $f->lastSentPostUri === 'https://example.com/post1'
            );
        });
    },
);
