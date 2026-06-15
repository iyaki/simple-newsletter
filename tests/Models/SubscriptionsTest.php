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
use SimpleNewsletter\Models\Subscriptions;

/**
 * @throws EndUserException
 * @throws \Random\RandomException
 */
it('throws on invalid URI in add', function (): void {
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    $feeds = \Mockery::mock(Feeds::class);
    $newsletter = \Mockery::mock(Newsletter::class);
    $auth = \Mockery::mock(Auth::class);

    $subs = new Subscriptions($subscriptionsDAO, $feeds, $newsletter, $auth);

    $subs->add('not-a-uri', 'user@example.com');
})->throws(EndUserException::class, 'Invalid Feed URI');

/**
 * @throws EndUserException
 * @throws \Random\RandomException
 */
it('throws on invalid email in add', function (): void {
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    $feeds = \Mockery::mock(Feeds::class);
    $newsletter = \Mockery::mock(Newsletter::class);
    $auth = \Mockery::mock(Auth::class);

    $now = new DateTimeImmutable();
    $feed = new Feed(new FeedMetadata('https://example.com/feed', 'Test Feed', 'https://example.com', $now));

    $feeds->shouldReceive('retrieve')->once()->with('https://example.com/feed')->andReturn($feed);

    $subs = new Subscriptions($subscriptionsDAO, $feeds, $newsletter, $auth);

    $subs->add('https://example.com/feed', 'not-an-email');
})->throws(EndUserException::class, 'Invalid email address');

/**
 * @throws EndUserException
 * @throws \Random\RandomException
 */
it('calls feeds->retrieve and newsletter->sendConfirmation on valid input', function (): void {
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    $feeds = \Mockery::mock(Feeds::class);
    $newsletter = \Mockery::mock(Newsletter::class);
    $auth = \Mockery::mock(Auth::class);

    $now = new DateTimeImmutable();
    $feedUri = 'https://example.com/feed';
    $email = 'user@example.com';
    $feed = new Feed(new FeedMetadata($feedUri, 'Test Feed', 'https://example.com', $now));

    $feeds->shouldReceive('retrieve')->once()->with($feedUri)->andReturn($feed);

    $subscriptionsDAO->shouldReceive('find')->once()->with($feedUri, $email)->andReturn(null);

    $subscriptionsDAO
        ->shouldReceive('new')
        ->once()
        ->with(\Mockery::on(
            fn (Subscription $sub): bool => $sub->feedUri === $feedUri && $sub->email === $email && ! $sub->active,
        ));

    $newsletter
        ->shouldReceive('sendConfirmation')
        ->once()
        ->with(
            $feed,
            \Mockery::on(fn (Subscription $sub): bool => $sub->feedUri === $feedUri && $sub->email === $email),
        );

    $subs = new Subscriptions($subscriptionsDAO, $feeds, $newsletter, $auth);

    $subs->add($feedUri, $email);
});

/**
 * @throws EndUserException
 * @throws \Random\RandomException
 */
it('throws when subscription already active in add', function (): void {
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    $feeds = \Mockery::mock(Feeds::class);
    $newsletter = \Mockery::mock(Newsletter::class);
    $auth = \Mockery::mock(Auth::class);

    $now = new DateTimeImmutable();
    $feedUri = 'https://example.com/feed';
    $email = 'user@example.com';
    $feed = new Feed(new FeedMetadata($feedUri, 'Test Feed', 'https://example.com', $now));
    $existingSub = new Subscription($feedUri, $email, true);

    $feeds->shouldReceive('retrieve')->once()->with($feedUri)->andReturn($feed);

    $subscriptionsDAO->shouldReceive('find')->once()->with($feedUri, $email)->andReturn($existingSub);

    $subscriptionsDAO->shouldNotReceive('new');
    $newsletter->shouldNotReceive('sendConfirmation');

    $subs = new Subscriptions($subscriptionsDAO, $feeds, $newsletter, $auth);

    $subs->add($feedUri, $email);
})->throws(EndUserException::class, 'You are already subscribed to this feed.');

/**
 * @throws EndUserException
 * @throws \Random\RandomException
 */
it('activates subscription on valid confirm token', function (): void {
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    $feeds = \Mockery::mock(Feeds::class);
    $newsletter = \Mockery::mock(Newsletter::class);
    $auth = \Mockery::mock(Auth::class);

    $feedUri = 'https://example.com/feed';
    $email = 'user@example.com';
    $token = 'valid-token';
    $subscription = new Subscription($feedUri, $email, false);

    $auth->shouldReceive('verify')->once()->with($email, $token)->andReturn(true);

    $subscriptionsDAO->shouldReceive('find')->once()->with($feedUri, $email)->andReturn($subscription);

    $subscriptionsDAO->shouldReceive('activate')->once()->with($subscription);

    $subs = new Subscriptions($subscriptionsDAO, $feeds, $newsletter, $auth);

    $subs->confirm($feedUri, $email, $token);
});

/**
 * @throws EndUserException
 * @throws \Random\RandomException
 */
it('throws on invalid confirm token', function (): void {
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    $feeds = \Mockery::mock(Feeds::class);
    $newsletter = \Mockery::mock(Newsletter::class);
    $auth = \Mockery::mock(Auth::class);

    $feedUri = 'https://example.com/feed';
    $email = 'user@example.com';

    $auth->shouldReceive('verify')->once()->with($email, 'bad-token')->andReturn(false);

    $subscriptionsDAO->shouldNotReceive('find');
    $subscriptionsDAO->shouldNotReceive('activate');

    $subs = new Subscriptions($subscriptionsDAO, $feeds, $newsletter, $auth);

    $subs->confirm($feedUri, $email, 'bad-token');
})->throws(EndUserException::class, 'Invalid token');

/**
 * @throws EndUserException
 * @throws \Random\RandomException
 */
it('throws when subscription not found in confirm', function (): void {
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    $feeds = \Mockery::mock(Feeds::class);
    $newsletter = \Mockery::mock(Newsletter::class);
    $auth = \Mockery::mock(Auth::class);

    $feedUri = 'https://example.com/feed';
    $email = 'user@example.com';

    $auth->shouldReceive('verify')->once()->with($email, 'valid-token')->andReturn(true);

    $subscriptionsDAO->shouldReceive('find')->once()->with($feedUri, $email)->andReturn(null);

    $subscriptionsDAO->shouldNotReceive('activate');

    $subs = new Subscriptions($subscriptionsDAO, $feeds, $newsletter, $auth);

    $subs->confirm($feedUri, $email, 'valid-token');
})->throws(EndUserException::class, 'Subscription not found');

/**
 * @throws EndUserException
 * @throws \Random\RandomException
 */
it('deactivates subscription on valid cancel token', function (): void {
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    $feeds = \Mockery::mock(Feeds::class);
    $newsletter = \Mockery::mock(Newsletter::class);
    $auth = \Mockery::mock(Auth::class);

    $feedUri = 'https://example.com/feed';
    $email = 'user@example.com';
    $token = 'valid-token';
    $subscription = new Subscription($feedUri, $email, true);

    $auth->shouldReceive('verify')->once()->with($email, $token)->andReturn(true);

    $subscriptionsDAO->shouldReceive('find')->once()->with($feedUri, $email)->andReturn($subscription);

    $subscriptionsDAO->shouldReceive('deactivate')->once()->with($subscription);

    $subs = new Subscriptions($subscriptionsDAO, $feeds, $newsletter, $auth);

    $subs->cancel($feedUri, $email, $token);
});

/**
 * @throws EndUserException
 * @throws \Random\RandomException
 */
it('throws on invalid cancel token', function (): void {
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    $feeds = \Mockery::mock(Feeds::class);
    $newsletter = \Mockery::mock(Newsletter::class);
    $auth = \Mockery::mock(Auth::class);

    $feedUri = 'https://example.com/feed';
    $email = 'user@example.com';

    $auth->shouldReceive('verify')->once()->with($email, 'bad-token')->andReturn(false);

    $subscriptionsDAO->shouldNotReceive('find');
    $subscriptionsDAO->shouldNotReceive('deactivate');

    $subs = new Subscriptions($subscriptionsDAO, $feeds, $newsletter, $auth);

    $subs->cancel($feedUri, $email, 'bad-token');
})->throws(EndUserException::class, 'Invalid token');

/**
 * @throws EndUserException
 * @throws \Random\RandomException
 */
it('throws when subscription not found in cancel', function (): void {
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    $feeds = \Mockery::mock(Feeds::class);
    $newsletter = \Mockery::mock(Newsletter::class);
    $auth = \Mockery::mock(Auth::class);

    $feedUri = 'https://example.com/feed';
    $email = 'user@example.com';

    $auth->shouldReceive('verify')->once()->with($email, 'valid-token')->andReturn(true);

    $subscriptionsDAO->shouldReceive('find')->once()->with($feedUri, $email)->andReturn(null);

    $subscriptionsDAO->shouldNotReceive('deactivate');

    $subs = new Subscriptions($subscriptionsDAO, $feeds, $newsletter, $auth);

    $subs->cancel($feedUri, $email, 'valid-token');
})->throws(EndUserException::class, 'Subscription not found');

/**
 * @throws EndUserException
 * @throws \Random\RandomException
 */
it('sendScheduled gets scheduled feeds, fetches posts, and sends to subscribers', function (): void {
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    $feeds = \Mockery::mock(Feeds::class);
    $newsletter = \Mockery::mock(Newsletter::class);
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

    $newsletter->shouldReceive('sendPostToSubscribers')->once()->with($feedWithPosts, $post1, $activeSub1, $activeSub2);

    $feeds->shouldReceive('updateLastSentPost')->once()->with($feedWithPosts, $post1);

    $subs = new Subscriptions($subscriptionsDAO, $feeds, $newsletter, $auth);

    $subs->sendScheduled($datetime);
});

/**
 * @throws EndUserException
 * @throws \Random\RandomException
 */
it('sendScheduled skips already-sent posts', function (): void {
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    $feeds = \Mockery::mock(Feeds::class);
    $newsletter = \Mockery::mock(Newsletter::class);
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
    $newsletter->shouldNotReceive('sendPostToSubscribers');
    $feeds->shouldNotReceive('updateLastSentPost');

    $subs = new Subscriptions($subscriptionsDAO, $feeds, $newsletter, $auth);

    $subs->sendScheduled($datetime);
});

/**
 * @throws EndUserException
 * @throws \InvalidArgumentException
 * @throws \Random\RandomException
 */
it('sendScheduled handles multiple scheduled feeds', function (): void {
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    $feeds = \Mockery::mock(Feeds::class);
    $newsletter = \Mockery::mock(Newsletter::class);
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
        ->andReturnUsing(function (Feed $feed) use ($feed1, $feed2, $feedWithPosts1, $feedWithPosts2): ?Feed {
            if ($feed === $feed1) {
                return $feedWithPosts1;
            }
            if ($feed === $feed2) {
                return $feedWithPosts2;
            }

            return null;
        });

    $subscriptionsDAO
        ->shouldReceive('findActiveSubscriptionsFor')
        ->times(2)
        /** @return array<int, \SimpleNewsletter\Data\Subscription> */
        ->andReturnUsing(function (Feed $feedWithPosts) use ($feedWithPosts1, $feedWithPosts2, $sub1, $sub2): array {
            if ($feedWithPosts === $feedWithPosts1) {
                return [$sub1];
            }
            if ($feedWithPosts === $feedWithPosts2) {
                return [$sub2];
            }

            return [];
        });

    $newsletter
        ->shouldReceive('sendPostToSubscribers')
        ->times(2)
        ->andReturnUsing(function (Feed $feed, Post $post, Subscription ...$subs) use (
            $feedWithPosts1,
            $feedWithPosts2,
            $post1,
            $post2,
            $sub1,
            $sub2,
        ): void {
            if ($feed === $feedWithPosts1 && $post === $post1 && $subs === [$sub1]) {
                return;
            }
            if ($feed === $feedWithPosts2 && $post === $post2 && $subs === [$sub2]) {
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

    $subs = new Subscriptions($subscriptionsDAO, $feeds, $newsletter, $auth);

    $subs->sendScheduled($datetime);
});
