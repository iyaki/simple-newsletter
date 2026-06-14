<?php

declare(strict_types=1);

use SimpleNewsletter\Components\Auth;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\Post;
use SimpleNewsletter\Data\Subscription;
use SimpleNewsletter\Data\SubscriptionsDAO;
use SimpleNewsletter\Models\Feeds;
use SimpleNewsletter\Models\Newsletter;
use SimpleNewsletter\Models\Subscriptions;

beforeEach(function () {
    $this->subscriptionsDAO = $this->createMock(SubscriptionsDAO::class);
    $this->feeds = $this->createMock(Feeds::class);
    $this->newsletter = $this->createMock(Newsletter::class);
    $this->auth = $this->createMock(Auth::class);
});

it('throws on invalid URI in add', function () {
    $subs = new Subscriptions($this->subscriptionsDAO, $this->feeds, $this->newsletter, $this->auth);

    $subs->add('not-a-uri', 'user@example.com');
})->throws(EndUserException::class, 'Invalid Feed URI');

it('throws on invalid email in add', function () {
    $now = new DateTimeImmutable();
    $feed = new Feed('https://example.com/feed', 'Test Feed', 'https://example.com', $now);

    $this->feeds->expects($this->once())->method('retrieve')->with('https://example.com/feed')->willReturn($feed);

    $subs = new Subscriptions($this->subscriptionsDAO, $this->feeds, $this->newsletter, $this->auth);

    $subs->add('https://example.com/feed', 'not-an-email');
})->throws(EndUserException::class, 'Invalid email address');

it('calls feeds->retrieve and newsletter->sendConfirmation on valid input', function () {
    $now = new DateTimeImmutable();
    $feedUri = 'https://example.com/feed';
    $email = 'user@example.com';
    $feed = new Feed($feedUri, 'Test Feed', 'https://example.com', $now);

    $this->feeds->expects($this->once())->method('retrieve')->with($feedUri)->willReturn($feed);

    $this->subscriptionsDAO->expects($this->once())->method('find')->with($feedUri, $email)->willReturn(null);

    $this->subscriptionsDAO
        ->expects($this->once())
        ->method('new')
        ->with($this->callback(
            fn (Subscription $sub): bool => $sub->feedUri === $feedUri && $sub->email === $email && ! $sub->active,
        ));

    $this->newsletter
        ->expects($this->once())
        ->method('sendConfirmation')
        ->with(
            $feed,
            $this->callback(fn (Subscription $sub): bool => $sub->feedUri === $feedUri && $sub->email === $email),
        );

    $subs = new Subscriptions($this->subscriptionsDAO, $this->feeds, $this->newsletter, $this->auth);

    $subs->add($feedUri, $email);
});

it('throws when subscription already active in add', function () {
    $now = new DateTimeImmutable();
    $feedUri = 'https://example.com/feed';
    $email = 'user@example.com';
    $feed = new Feed($feedUri, 'Test Feed', 'https://example.com', $now);
    $existingSub = new Subscription($feedUri, $email, true);

    $this->feeds->expects($this->once())->method('retrieve')->with($feedUri)->willReturn($feed);

    $this->subscriptionsDAO->expects($this->once())->method('find')->with($feedUri, $email)->willReturn($existingSub);

    $this->subscriptionsDAO->expects($this->never())->method('new');
    $this->newsletter->expects($this->never())->method('sendConfirmation');

    $subs = new Subscriptions($this->subscriptionsDAO, $this->feeds, $this->newsletter, $this->auth);

    $subs->add($feedUri, $email);
})->throws(EndUserException::class, 'You are already subscribed to this feed.');

it('activates subscription on valid confirm token', function () {
    $feedUri = 'https://example.com/feed';
    $email = 'user@example.com';
    $token = 'valid-token';
    $subscription = new Subscription($feedUri, $email, false);

    $this->auth->expects($this->once())->method('verify')->with($email, $token)->willReturn(true);

    $this->subscriptionsDAO->expects($this->once())->method('find')->with($feedUri, $email)->willReturn($subscription);

    $this->subscriptionsDAO->expects($this->once())->method('activate')->with($subscription);

    $subs = new Subscriptions($this->subscriptionsDAO, $this->feeds, $this->newsletter, $this->auth);

    $subs->confirm($feedUri, $email, $token);
});

it('throws on invalid confirm token', function () {
    $feedUri = 'https://example.com/feed';
    $email = 'user@example.com';

    $this->auth->expects($this->once())->method('verify')->with($email, 'bad-token')->willReturn(false);

    $this->subscriptionsDAO->expects($this->never())->method('find');
    $this->subscriptionsDAO->expects($this->never())->method('activate');

    $subs = new Subscriptions($this->subscriptionsDAO, $this->feeds, $this->newsletter, $this->auth);

    $subs->confirm($feedUri, $email, 'bad-token');
})->throws(EndUserException::class, 'Invalid token');

it('throws when subscription not found in confirm', function () {
    $feedUri = 'https://example.com/feed';
    $email = 'user@example.com';

    $this->auth->expects($this->once())->method('verify')->with($email, 'valid-token')->willReturn(true);

    $this->subscriptionsDAO->expects($this->once())->method('find')->with($feedUri, $email)->willReturn(null);

    $this->subscriptionsDAO->expects($this->never())->method('activate');

    $subs = new Subscriptions($this->subscriptionsDAO, $this->feeds, $this->newsletter, $this->auth);

    $subs->confirm($feedUri, $email, 'valid-token');
})->throws(EndUserException::class, 'Subscription not found');

it('deactivates subscription on valid cancel token', function () {
    $feedUri = 'https://example.com/feed';
    $email = 'user@example.com';
    $token = 'valid-token';
    $subscription = new Subscription($feedUri, $email, true);

    $this->auth->expects($this->once())->method('verify')->with($email, $token)->willReturn(true);

    $this->subscriptionsDAO->expects($this->once())->method('find')->with($feedUri, $email)->willReturn($subscription);

    $this->subscriptionsDAO->expects($this->once())->method('deactivate')->with($subscription);

    $subs = new Subscriptions($this->subscriptionsDAO, $this->feeds, $this->newsletter, $this->auth);

    $subs->cancel($feedUri, $email, $token);
});

it('throws on invalid cancel token', function () {
    $feedUri = 'https://example.com/feed';
    $email = 'user@example.com';

    $this->auth->expects($this->once())->method('verify')->with($email, 'bad-token')->willReturn(false);

    $this->subscriptionsDAO->expects($this->never())->method('find');
    $this->subscriptionsDAO->expects($this->never())->method('deactivate');

    $subs = new Subscriptions($this->subscriptionsDAO, $this->feeds, $this->newsletter, $this->auth);

    $subs->cancel($feedUri, $email, 'bad-token');
})->throws(EndUserException::class, 'Invalid token');

it('throws when subscription not found in cancel', function () {
    $feedUri = 'https://example.com/feed';
    $email = 'user@example.com';

    $this->auth->expects($this->once())->method('verify')->with($email, 'valid-token')->willReturn(true);

    $this->subscriptionsDAO->expects($this->once())->method('find')->with($feedUri, $email)->willReturn(null);

    $this->subscriptionsDAO->expects($this->never())->method('deactivate');

    $subs = new Subscriptions($this->subscriptionsDAO, $this->feeds, $this->newsletter, $this->auth);

    $subs->cancel($feedUri, $email, 'valid-token');
})->throws(EndUserException::class, 'Subscription not found');

it('sendScheduled gets scheduled feeds, fetches posts, and sends to subscribers', function () {
    $datetime = new DateTimeImmutable();
    $feedUri = 'https://example.com/feed';

    $scheduledFeed = new Feed($feedUri, 'Scheduled Feed', 'https://example.com', $datetime);

    $post1 = new Post('https://example.com/post1', 'Post 1', 'Content 1');
    $feedWithPosts = new Feed($feedUri, 'Scheduled Feed', 'https://example.com', $datetime, null, [$post1]);

    $activeSub1 = new Subscription($feedUri, 'user1@example.com', true);
    $activeSub2 = new Subscription($feedUri, 'user2@example.com', true);

    $this->feeds->expects($this->once())->method('getScheduled')->with($datetime)->willReturn([$scheduledFeed]);

    $this->feeds->expects($this->once())->method('retrieveWithPosts')->with($scheduledFeed)->willReturn($feedWithPosts);

    $this->subscriptionsDAO
        ->expects($this->once())
        ->method('findActiveSubscriptionsFor')
        ->with($feedWithPosts)
        ->willReturn([$activeSub1, $activeSub2]);

    $this->newsletter
        ->expects($this->once())
        ->method('sendPostToSubscribers')
        ->with($feedWithPosts, $post1, $activeSub1, $activeSub2);

    $this->feeds->expects($this->once())->method('updateLastSentPost')->with($feedWithPosts, $post1);

    $subs = new Subscriptions($this->subscriptionsDAO, $this->feeds, $this->newsletter, $this->auth);

    $subs->sendScheduled($datetime);
});

it('sendScheduled skips already-sent posts', function () {
    $datetime = new DateTimeImmutable();
    $feedUri = 'https://example.com/feed';

    $scheduledFeed = new Feed($feedUri, 'Scheduled Feed', 'https://example.com', $datetime);

    // lastSentPostUri matches the post URI so it should be skipped
    $post1 = new Post('https://example.com/post1', 'Post 1', 'Content 1');
    $feedWithPosts = new Feed(
        $feedUri,
        'Scheduled Feed',
        'https://example.com',
        $datetime,
        'https://example.com/post1',
        [$post1],
    );

    $this->feeds->expects($this->once())->method('getScheduled')->with($datetime)->willReturn([$scheduledFeed]);

    $this->feeds->expects($this->once())->method('retrieveWithPosts')->with($scheduledFeed)->willReturn($feedWithPosts);

    $this->subscriptionsDAO->expects($this->never())->method('findActiveSubscriptionsFor');
    $this->newsletter->expects($this->never())->method('sendPostToSubscribers');
    $this->feeds->expects($this->never())->method('updateLastSentPost');

    $subs = new Subscriptions($this->subscriptionsDAO, $this->feeds, $this->newsletter, $this->auth);

    $subs->sendScheduled($datetime);
});

it('sendScheduled handles multiple scheduled feeds', function () {
    $datetime = new DateTimeImmutable();

    $feed1 = new Feed('https://example.com/feed1', 'Feed 1', 'https://example.com', $datetime);
    $feed2 = new Feed('https://example.com/feed2', 'Feed 2', 'https://example.com', $datetime);

    $post1 = new Post('https://example.com/post1', 'Post 1', 'Content 1');
    $post2 = new Post('https://example.com/post2', 'Post 2', 'Content 2');

    $feedWithPosts1 = new Feed('https://example.com/feed1', 'Feed 1', 'https://example.com', $datetime, null, [$post1]);
    $feedWithPosts2 = new Feed('https://example.com/feed2', 'Feed 2', 'https://example.com', $datetime, null, [$post2]);

    $sub1 = new Subscription('https://example.com/feed1', 'user1@example.com', true);
    $sub2 = new Subscription('https://example.com/feed2', 'user2@example.com', true);

    $this->feeds->expects($this->once())->method('getScheduled')->with($datetime)->willReturn([$feed1, $feed2]);

    $this->feeds
        ->expects($this->exactly(2))
        ->method('retrieveWithPosts')
        ->willReturnMap([
            [$feed1, $feedWithPosts1],
            [$feed2, $feedWithPosts2],
        ]);

    $this->subscriptionsDAO
        ->expects($this->exactly(2))
        ->method('findActiveSubscriptionsFor')
        ->willReturnMap([
            [$feedWithPosts1, [$sub1]],
            [$feedWithPosts2, [$sub2]],
        ]);

    $this->newsletter
        ->expects($this->exactly(2))
        ->method('sendPostToSubscribers')
        ->willReturnMap([
            [$feedWithPosts1, $post1, $sub1, null],
            [$feedWithPosts2, $post2, $sub2, null],
        ]);

    $this->feeds
        ->expects($this->exactly(2))
        ->method('updateLastSentPost')
        ->willReturnMap([
            [$feedWithPosts1, $post1, null],
            [$feedWithPosts2, $post2, null],
        ]);

    $subs = new Subscriptions($this->subscriptionsDAO, $this->feeds, $this->newsletter, $this->auth);

    $subs->sendScheduled($datetime);
});
