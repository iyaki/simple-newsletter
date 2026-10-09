<?php

declare(strict_types=1);

use SimpleNewsletter\Components\Auth;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\FeedMetadata;
use SimpleNewsletter\Data\Post;
use SimpleNewsletter\Data\Subscription;
use SimpleNewsletter\Data\SubscriptionsDAO;
use SimpleNewsletter\Models\Feeds;
use SimpleNewsletter\Models\Newsletter;
use SimpleNewsletter\Models\NewsletterDelivery;

/**
 * @throws \Random\RandomException
 */
beforeEach(function (): void {
    \Mockery::close();
});

/**
 * @throws EndUserException
 * @throws \Random\RandomException
 */
it('sendScheduled gets scheduled feeds, fetches posts, and sends to subscribers', function (): void {
    /** @var SubscriptionsDAO&\Mockery\MockInterface $subscriptionsDAO */
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    /** @var Feeds&\Mockery\MockInterface $feeds */
    $feeds = \Mockery::mock(Feeds::class);
    /** @var Newsletter&\Mockery\MockInterface $newsletter */
    $newsletter = \Mockery::mock(Newsletter::class);
    /** @var Auth&\Mockery\MockInterface $auth */
    $auth = \Mockery::mock(Auth::class);

    $datetime = new DateTimeImmutable();
    $feedUri = 'https://example.com/feed';

    $scheduledFeed = new Feed(new FeedMetadata($feedUri, 'Scheduled Feed', 'https://example.com', $datetime));

    $post1 = new Post('https://example.com/post1', 'Post 1', 'Content 1');
    $feedWithPosts = new Feed(
        new FeedMetadata($feedUri, 'Scheduled Feed', 'https://example.com', $datetime),
        lastSentPostUri: null,
        posts: [$post1],
    );

    $activeSub1 = new Subscription($feedUri, 'user1@example.com', true);
    $activeSub2 = new Subscription($feedUri, 'user2@example.com', true);

    $feeds->shouldReceive('getScheduled')->once()->with($datetime)->andReturn([$scheduledFeed]);

    $feeds->shouldReceive('retrieveWithPosts')->once()->with($scheduledFeed)->andReturn($feedWithPosts);

    $subscriptionsDAO
        ->shouldReceive('findActiveSubscriptionsFor')
        ->once()
        ->with($feedWithPosts)
        ->andReturn([$activeSub1, $activeSub2]);

    $newsletter->shouldReceive('sendPostsToSubscribers')->once()->with($feedWithPosts, [$post1], $activeSub1, $activeSub2);

    $feeds->shouldReceive('updateLastSentPost')->once()->with($feedWithPosts, $post1);

    $delivery = new NewsletterDelivery($subscriptionsDAO, $feeds, $newsletter);

    $delivery->sendScheduled($datetime);
});

/**
 * @throws EndUserException
 * @throws \Random\RandomException
 */
it('sendScheduled skips already-sent posts', function (): void {
    /** @var SubscriptionsDAO&\Mockery\MockInterface $subscriptionsDAO */
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    /** @var Feeds&\Mockery\MockInterface $feeds */
    $feeds = \Mockery::mock(Feeds::class);
    /** @var Newsletter&\Mockery\MockInterface $newsletter */
    $newsletter = \Mockery::mock(Newsletter::class);
    /** @var Auth&\Mockery\MockInterface $auth */
    $auth = \Mockery::mock(Auth::class);

    $datetime = new DateTimeImmutable();
    $feedUri = 'https://example.com/feed';

    $scheduledFeed = new Feed(new FeedMetadata($feedUri, 'Scheduled Feed', 'https://example.com', $datetime));

    // lastSentPostUri matches the post URI so it should be skipped
    $post1 = new Post('https://example.com/post1', 'Post 1', 'Content 1');
    $feedWithPosts = new Feed(
        metadata: new FeedMetadata($feedUri, 'Scheduled Feed', 'https://example.com', $datetime),
        lastSentPostUri: 'https://example.com/post1',
        posts: [$post1],
    );

    $feeds->shouldReceive('getScheduled')->once()->with($datetime)->andReturn([$scheduledFeed]);

    $feeds->shouldReceive('retrieveWithPosts')->once()->with($scheduledFeed)->andReturn($feedWithPosts);

    $subscriptionsDAO->shouldNotReceive('findActiveSubscriptionsFor');
    $newsletter->shouldNotReceive('sendPostsToSubscribers');
    $feeds->shouldNotReceive('updateLastSentPost');

    $delivery = new NewsletterDelivery($subscriptionsDAO, $feeds, $newsletter);

    $delivery->sendScheduled($datetime);
});

/**
 * Regression: when the newest post was already sent (lastSentPostUri points at
 * posts[0]), older posts must NOT be re-emailed. Previously `continue` skipped
 * the watermark and then sent the next (older) post, regressing the watermark.
 *
 * @throws EndUserException
 * @throws \Random\RandomException
 */
it('sendScheduled does not resend older posts once the newest is sent', function (): void {
    /** @var SubscriptionsDAO&\Mockery\MockInterface $subscriptionsDAO */
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    /** @var Feeds&\Mockery\MockInterface $feeds */
    $feeds = \Mockery::mock(Feeds::class);
    /** @var Newsletter&\Mockery\MockInterface $newsletter */
    $newsletter = \Mockery::mock(Newsletter::class);
    /** @var Auth&\Mockery\MockInterface $auth */
    $auth = \Mockery::mock(Auth::class);

    $datetime = new DateTimeImmutable();
    $feedUri = 'https://example.com/feed';

    $scheduledFeed = new Feed(new FeedMetadata($feedUri, 'Scheduled Feed', 'https://example.com', $datetime));

    // Newest-first: the newest post was already sent (it is the watermark),
    // an older post is still present in the feed.
    $newest = new Post('https://example.com/newest', 'Newest', 'Content newest');
    $older = new Post('https://example.com/older', 'Older', 'Content older');
    $feedWithPosts = new Feed(
        metadata: new FeedMetadata($feedUri, 'Scheduled Feed', 'https://example.com', $datetime),
        lastSentPostUri: 'https://example.com/newest',
        posts: [$newest, $older],
    );

    $feeds->shouldReceive('getScheduled')->once()->with($datetime)->andReturn([$scheduledFeed]);
    $feeds->shouldReceive('retrieveWithPosts')->once()->with($scheduledFeed)->andReturn($feedWithPosts);

    $subscriptionsDAO->shouldNotReceive('findActiveSubscriptionsFor');
    $newsletter->shouldNotReceive('sendPostsToSubscribers');
    $feeds->shouldNotReceive('updateLastSentPost');

    $delivery = new NewsletterDelivery($subscriptionsDAO, $feeds, $newsletter);

    $delivery->sendScheduled($datetime);
});

/**
 * @throws EndUserException
 * @throws \InvalidArgumentException
 * @throws \Random\RandomException
 */
it('sendScheduled handles multiple scheduled feeds', function (): void {
    /** @var SubscriptionsDAO&\Mockery\MockInterface $subscriptionsDAO */
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    /** @var Feeds&\Mockery\MockInterface $feeds */
    $feeds = \Mockery::mock(Feeds::class);
    /** @var Newsletter&\Mockery\MockInterface $newsletter */
    $newsletter = \Mockery::mock(Newsletter::class);
    /** @var Auth&\Mockery\MockInterface $auth */
    $auth = \Mockery::mock(Auth::class);

    $datetime = new DateTimeImmutable();

    $feed1 = new Feed(new FeedMetadata('https://example.com/feed1', 'Feed 1', 'https://example.com', $datetime));
    $feed2 = new Feed(new FeedMetadata('https://example.com/feed2', 'Feed 2', 'https://example.com', $datetime));

    $post1 = new Post('https://example.com/post1', 'Post 1', 'Content 1');
    $post2 = new Post('https://example.com/post2', 'Post 2', 'Content 2');

    $feedWithPosts1 = new Feed(
        new FeedMetadata('https://example.com/feed1', 'Feed 1', 'https://example.com', $datetime),
        posts: [$post1],
    );
    $feedWithPosts2 = new Feed(
        new FeedMetadata('https://example.com/feed2', 'Feed 2', 'https://example.com', $datetime),
        posts: [$post2],
    );

    $sub1 = new Subscription('https://example.com/feed1', 'user1@example.com', true);
    $sub2 = new Subscription('https://example.com/feed2', 'user2@example.com', true);

    $feeds->shouldReceive('getScheduled')->once()->with($datetime)->andReturn([$feed1, $feed2]);

    $feeds
        ->shouldReceive('retrieveWithPosts')
        ->times(2)
        ->andReturnUsing(fn (Feed $feed): ?Feed => match ($feed->metadata->uri) {
            'https://example.com/feed1' => $feedWithPosts1,
            'https://example.com/feed2' => $feedWithPosts2,
            default => null,
        });

    $subscriptionsDAO
        ->shouldReceive('findActiveSubscriptionsFor')
        ->times(2)
        /** @return array<int, \SimpleNewsletter\Data\Subscription> */
        ->andReturnUsing(fn (Feed $feed): array => match ($feed->metadata->uri) {
            'https://example.com/feed1' => [$sub1],
            'https://example.com/feed2' => [$sub2],
            default => [],
        });

    $newsletter
        ->shouldReceive('sendPostsToSubscribers')
        ->times(2)
        ->andReturnUsing(function (Feed $feed, array $posts, Subscription ...$subs) use (
            $feedWithPosts1,
            $feedWithPosts2,
            $post1,
            $post2,
            $sub1,
            $sub2,
        ): void {
            if ($feed === $feedWithPosts1 && $posts === [$post1] && $subs === [$sub1]) {
                return;
            }
            if ($feed === $feedWithPosts2 && $posts === [$post2] && $subs === [$sub2]) {
                return;
            }
        });

    $feeds
        ->shouldReceive('updateLastSentPost')
        ->times(2)
        ->andReturnUsing(function (Feed $feed, Post $post) use (
            $feedWithPosts1,
            $feedWithPosts2,
            $post1,
            $post2,
        ): void {
            if ($feed === $feedWithPosts1 && $post === $post1) {
                return;
            }
            if ($feed === $feedWithPosts2 && $post === $post2) {
                return;
            }
        });

    $delivery = new NewsletterDelivery($subscriptionsDAO, $feeds, $newsletter);

    $delivery->sendScheduled($datetime);
});

/**
 * Sends every post newer than the watermark in a single email (newest-first),
 * advancing the watermark to the newest sent post.
 *
 * @throws EndUserException
 * @throws \Random\RandomException
 */
it('sendScheduled sends all new posts in one email', function (): void {
    /** @var SubscriptionsDAO&\Mockery\MockInterface $subscriptionsDAO */
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    /** @var Feeds&\Mockery\MockInterface $feeds */
    $feeds = \Mockery::mock(Feeds::class);
    /** @var Newsletter&\Mockery\MockInterface $newsletter */
    $newsletter = \Mockery::mock(Newsletter::class);
    /** @var Auth&\Mockery\MockInterface $auth */
    $auth = \Mockery::mock(Auth::class);

    $datetime = new DateTimeImmutable();
    $feedUri = 'https://example.com/feed';

    $scheduledFeed = new Feed(new FeedMetadata($feedUri, 'Scheduled Feed', 'https://example.com', $datetime));

    // Newest-first; the oldest is the watermark (already sent).
    $newest = new Post('https://example.com/newest', 'Newest', 'Newest content');
    $middle = new Post('https://example.com/middle', 'Middle', 'Middle content');
    $watermark = new Post('https://example.com/old', 'Old', 'Old content');
    $feedWithPosts = new Feed(
        metadata: new FeedMetadata($feedUri, 'Scheduled Feed', 'https://example.com', $datetime),
        lastSentPostUri: 'https://example.com/old',
        posts: [$newest, $middle, $watermark],
    );

    $activeSub = new Subscription($feedUri, 'user@example.com', true);

    $feeds->shouldReceive('getScheduled')->once()->with($datetime)->andReturn([$scheduledFeed]);
    $feeds->shouldReceive('retrieveWithPosts')->once()->with($scheduledFeed)->andReturn($feedWithPosts);

    $subscriptionsDAO->shouldReceive('findActiveSubscriptionsFor')->once()->with($feedWithPosts)->andReturn([$activeSub]);

    // Exactly one email containing both new posts (newest-first).
    $newsletter->shouldReceive('sendPostsToSubscribers')->once()->with($feedWithPosts, [$newest, $middle], $activeSub);

    // Watermark advances to the newest sent post.
    $feeds->shouldReceive('updateLastSentPost')->once()->with($feedWithPosts, $newest);

    $delivery = new NewsletterDelivery($subscriptionsDAO, $feeds, $newsletter);

    $delivery->sendScheduled($datetime);
});

/**
 * Regression: a feed whose fetch fails (dead origin, 404, malformed XML) must
 * not abort the batch - later co-scheduled feeds still get delivered.
 *
 * @throws \Random\RandomException
 */
it('sendScheduled keeps delivering later feeds when one feed fetch fails', function (): void {
    /** @var SubscriptionsDAO&\Mockery\MockInterface $subscriptionsDAO */
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    /** @var Feeds&\Mockery\MockInterface $feeds */
    $feeds = \Mockery::mock(Feeds::class);
    /** @var Newsletter&\Mockery\MockInterface $newsletter */
    $newsletter = \Mockery::mock(Newsletter::class);
    /** @var Auth&\Mockery\MockInterface $auth */
    $auth = \Mockery::mock(Auth::class);

    $datetime = new DateTimeImmutable();

    $deadFeed = new Feed(new FeedMetadata('https://dead.example.com/feed', 'Dead Feed', 'https://dead.example.com', $datetime));
    $healthyFeed = new Feed(new FeedMetadata('https://good.example.com/feed', 'Good Feed', 'https://good.example.com', $datetime));

    $post = new Post('https://good.example.com/post1', 'Post 1', 'Content 1');
    $feedWithPosts = new Feed(
        new FeedMetadata('https://good.example.com/feed', 'Good Feed', 'https://good.example.com', $datetime),
        lastSentPostUri: null,
        posts: [$post],
    );
    $subscriber = new Subscription('https://good.example.com/feed', 'user@example.com', true);

    $feeds->shouldReceive('getScheduled')->once()->with($datetime)->andReturn([$deadFeed, $healthyFeed]);
    $feeds->shouldReceive('retrieveWithPosts')->once()->with($deadFeed)
        ->andThrow(new EndUserException('The feed could not be loaded. Please check the URL and try again.'));
    $feeds->shouldReceive('retrieveWithPosts')->once()->with($healthyFeed)->andReturn($feedWithPosts);
    $subscriptionsDAO->shouldReceive('findActiveSubscriptionsFor')->once()->with($feedWithPosts)->andReturn([$subscriber]);
    $newsletter->shouldReceive('sendPostsToSubscribers')->once()->with($feedWithPosts, [$post], $subscriber);
    $feeds->shouldReceive('updateLastSentPost')->once()->with($feedWithPosts, $post);

    $delivery = new NewsletterDelivery($subscriptionsDAO, $feeds, $newsletter);

    $delivery->sendScheduled($datetime);

    expect(true)->toBeTrue();
});

/**
 * Regression: when the stored watermark URI is absent from the fetched
 * document (rolling window overflow or publisher drop), the delivery state
 * is unknown - only the newest post may be sent, never the whole backlog.
 *
 * @throws \Random\RandomException
 */
it('sendScheduled sends only the newest post when the watermark is missing from the feed', function (): void {
    /** @var SubscriptionsDAO&\Mockery\MockInterface $subscriptionsDAO */
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    /** @var Feeds&\Mockery\MockInterface $feeds */
    $feeds = \Mockery::mock(Feeds::class);
    /** @var Newsletter&\Mockery\MockInterface $newsletter */
    $newsletter = \Mockery::mock(Newsletter::class);
    /** @var Auth&\Mockery\MockInterface $auth */
    $auth = \Mockery::mock(Auth::class);

    $datetime = new DateTimeImmutable();
    $feedUri = 'https://example.com/feed';

    $scheduledFeed = new Feed(new FeedMetadata($feedUri, 'Feed', 'https://example.com', $datetime));

    $oldest = new Post('https://example.com/a-old', 'Old', 'Old content');
    $middle = new Post('https://example.com/b-mid', 'Mid', 'Mid content');
    $newest = new Post('https://example.com/c-new', 'New', 'New content');
    // Watermark points at an entry the publisher dropped from the document.
    $feedWithPosts = new Feed(
        new FeedMetadata($feedUri, 'Feed', 'https://example.com', $datetime),
        lastSentPostUri: 'https://example.com/zz-dropped',
        posts: [$newest, $middle, $oldest],
    );
    $subscriber = new Subscription($feedUri, 'user@example.com', true);

    $feeds->shouldReceive('getScheduled')->once()->with($datetime)->andReturn([$scheduledFeed]);
    $feeds->shouldReceive('retrieveWithPosts')->once()->with($scheduledFeed)->andReturn($feedWithPosts);
    $subscriptionsDAO->shouldReceive('findActiveSubscriptionsFor')->once()->with($feedWithPosts)->andReturn([$subscriber]);
    // Newest post only - not the full three-post backlog.
    $newsletter->shouldReceive('sendPostsToSubscribers')->once()->with($feedWithPosts, [$newest], $subscriber);
    $feeds->shouldReceive('updateLastSentPost')->once()->with($feedWithPosts, $newest);

    $delivery = new NewsletterDelivery($subscriptionsDAO, $feeds, $newsletter);

    $delivery->sendScheduled($datetime);
});
