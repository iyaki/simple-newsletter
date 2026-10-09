<?php

declare(strict_types=1);

use SimpleNewsletter\Components\Auth;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\FeedMetadata;
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
    /** @var SubscriptionsDAO $subscriptionsDAO */
    /** @var SubscriptionsDAO&\Mockery\MockInterface $subscriptionsDAO */
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    /** @var Feeds $feeds */
    /** @var Feeds&\Mockery\MockInterface $feeds */
    $feeds = \Mockery::mock(Feeds::class);
    /** @var Newsletter $newsletter */
    /** @var Newsletter&\Mockery\MockInterface $newsletter */
    $newsletter = \Mockery::mock(Newsletter::class);
    /** @var Auth $auth */
    /** @var Auth&\Mockery\MockInterface $auth */
    $auth = \Mockery::mock(Auth::class);

    $subs = new Subscriptions($subscriptionsDAO, $feeds, $newsletter, $auth);

    $subs->add('not-a-uri', 'user@example.com');
})->throws(EndUserException::class, 'Invalid Feed URI');

/**
 * @throws EndUserException
 * @throws \Random\RandomException
 */
it('throws on invalid email in add', function (): void {
    /** @var SubscriptionsDAO&\Mockery\MockInterface $subscriptionsDAO */
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    /** @var Feeds&\Mockery\MockInterface $feeds */
    $feeds = \Mockery::mock(Feeds::class);
    /** @var Newsletter&\Mockery\MockInterface $newsletter */
    $newsletter = \Mockery::mock(Newsletter::class);
    /** @var Auth&\Mockery\MockInterface $auth */
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
    /** @var SubscriptionsDAO&\Mockery\MockInterface $subscriptionsDAO */
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    /** @var Feeds&\Mockery\MockInterface $feeds */
    $feeds = \Mockery::mock(Feeds::class);
    /** @var Newsletter&\Mockery\MockInterface $newsletter */
    $newsletter = \Mockery::mock(Newsletter::class);
    /** @var Auth&\Mockery\MockInterface $auth */
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

    $auth->shouldReceive('newNonce')->once()->andReturn('nonce123');

    $newsletter
        ->shouldReceive('sendConfirmation')
        ->once()
        ->with(
            $feed,
            \Mockery::on(fn (Subscription $sub): bool => $sub->feedUri === $feedUri && $sub->email === $email),
        );

    $subscriptionsDAO->shouldReceive('markConfirmationSent')->once();

    $subs = new Subscriptions($subscriptionsDAO, $feeds, $newsletter, $auth);

    $subs->add($feedUri, $email);
});

test('add suppresses the confirmation email for a recently notified pending subscription', function (): void {
    /** @var SubscriptionsDAO&\Mockery\MockInterface $subscriptionsDAO */
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    /** @var Feeds&\Mockery\MockInterface $feeds */
    $feeds = \Mockery::mock(Feeds::class);
    /** @var Newsletter&\Mockery\MockInterface $newsletter */
    $newsletter = \Mockery::mock(Newsletter::class);
    /** @var Auth&\Mockery\MockInterface $auth */
    $auth = \Mockery::mock(Auth::class);

    $now = new DateTimeImmutable();
    $feedUri = 'https://example.com/feed';
    $email = 'user@example.com';
    $feed = new Feed(new FeedMetadata($feedUri, 'Test Feed', 'https://example.com', $now));

    $pending = new Subscription($feedUri, $email, false, tokenNonce: 'nonce', confirmationSentAt: \time() - 60);

    $feeds->shouldReceive('retrieve')->once()->with($feedUri)->andReturn($feed);
    $subscriptionsDAO->shouldReceive('find')->once()->with($feedUri, $email)->andReturn($pending);
    $newsletter->shouldNotReceive('sendConfirmation');
    $subscriptionsDAO->shouldNotReceive('markConfirmationSent');
    $subscriptionsDAO->shouldNotReceive('new');

    $subs = new Subscriptions($subscriptionsDAO, $feeds, $newsletter, $auth);

    $subs->add($feedUri, $email);

    expect(true)->toBeTrue();
});

test('add resends the confirmation email once the throttle interval has passed', function (): void {
    /** @var SubscriptionsDAO&\Mockery\MockInterface $subscriptionsDAO */
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    /** @var Feeds&\Mockery\MockInterface $feeds */
    $feeds = \Mockery::mock(Feeds::class);
    /** @var Newsletter&\Mockery\MockInterface $newsletter */
    $newsletter = \Mockery::mock(Newsletter::class);
    /** @var Auth&\Mockery\MockInterface $auth */
    $auth = \Mockery::mock(Auth::class);

    $now = new DateTimeImmutable();
    $feedUri = 'https://example.com/feed';
    $email = 'user@example.com';
    $feed = new Feed(new FeedMetadata($feedUri, 'Test Feed', 'https://example.com', $now));

    $stale = new Subscription($feedUri, $email, false, tokenNonce: 'nonce', confirmationSentAt: \time() - 3601);

    $feeds->shouldReceive('retrieve')->once()->with($feedUri)->andReturn($feed);
    $subscriptionsDAO->shouldReceive('find')->once()->with($feedUri, $email)->andReturn($stale);
    $newsletter->shouldReceive('sendConfirmation')->once()->with($feed, $stale);
    $subscriptionsDAO->shouldReceive('markConfirmationSent')->once()->with($stale);
    $subscriptionsDAO->shouldNotReceive('new');

    $subs = new Subscriptions($subscriptionsDAO, $feeds, $newsletter, $auth);

    $subs->add($feedUri, $email);

    expect(true)->toBeTrue();
});

/**
 * @throws EndUserException
 * @throws \Random\RandomException
 */
it('throws when subscription already active in add', function (): void {
    /** @var SubscriptionsDAO&\Mockery\MockInterface $subscriptionsDAO */
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    /** @var Feeds&\Mockery\MockInterface $feeds */
    $feeds = \Mockery::mock(Feeds::class);
    /** @var Newsletter&\Mockery\MockInterface $newsletter */
    $newsletter = \Mockery::mock(Newsletter::class);
    /** @var Auth&\Mockery\MockInterface $auth */
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
    /** @var SubscriptionsDAO&\Mockery\MockInterface $subscriptionsDAO */
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    /** @var Feeds&\Mockery\MockInterface $feeds */
    $feeds = \Mockery::mock(Feeds::class);
    /** @var Newsletter&\Mockery\MockInterface $newsletter */
    $newsletter = \Mockery::mock(Newsletter::class);
    /** @var Auth&\Mockery\MockInterface $auth */
    $auth = \Mockery::mock(Auth::class);

    $feedUri = 'https://example.com/feed';
    $email = 'user@example.com';
    $token = 'valid-token';
    $subscription = new Subscription($feedUri, $email, false);

    $auth->shouldReceive('tokenKey')->once()->with('confirm', $feedUri, $email, '')->andReturn('confirm|' . $feedUri . '|' . $email . '|');
    $auth->shouldReceive('verify')->once()->with('confirm|' . $feedUri . '|' . $email . '|', $token)->andReturn(true);

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
    /** @var SubscriptionsDAO&\Mockery\MockInterface $subscriptionsDAO */
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    /** @var Feeds&\Mockery\MockInterface $feeds */
    $feeds = \Mockery::mock(Feeds::class);
    /** @var Newsletter&\Mockery\MockInterface $newsletter */
    $newsletter = \Mockery::mock(Newsletter::class);
    /** @var Auth&\Mockery\MockInterface $auth */
    $auth = \Mockery::mock(Auth::class);

    $feedUri = 'https://example.com/feed';
    $email = 'user@example.com';
    $subscription = new Subscription($feedUri, $email, false);

    $subscriptionsDAO->shouldReceive('find')->once()->with($feedUri, $email)->andReturn($subscription);
    $auth->shouldReceive('tokenKey')->once()->with('confirm', $feedUri, $email, '')->andReturn('confirm|' . $feedUri . '|' . $email . '|');
    $auth->shouldReceive('verify')->once()->with('confirm|' . $feedUri . '|' . $email . '|', 'bad-token')->andReturn(false);
    $subscriptionsDAO->shouldNotReceive('activate');

    $subs = new Subscriptions($subscriptionsDAO, $feeds, $newsletter, $auth);

    $subs->confirm($feedUri, $email, 'bad-token');
})->throws(EndUserException::class, 'Invalid token');

/**
 * @throws EndUserException
 * @throws \Random\RandomException
 */
it('throws when subscription not found in confirm', function (): void {
    /** @var SubscriptionsDAO&\Mockery\MockInterface $subscriptionsDAO */
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    /** @var Feeds&\Mockery\MockInterface $feeds */
    $feeds = \Mockery::mock(Feeds::class);
    /** @var Newsletter&\Mockery\MockInterface $newsletter */
    $newsletter = \Mockery::mock(Newsletter::class);
    /** @var Auth&\Mockery\MockInterface $auth */
    $auth = \Mockery::mock(Auth::class);

    $feedUri = 'https://example.com/feed';
    $email = 'user@example.com';

    $subscriptionsDAO->shouldReceive('find')->once()->with($feedUri, $email)->andReturn(null);
    $auth->shouldNotReceive('verify');

    $subscriptionsDAO->shouldNotReceive('activate');

    $subs = new Subscriptions($subscriptionsDAO, $feeds, $newsletter, $auth);

    $subs->confirm($feedUri, $email, 'valid-token');
})->throws(EndUserException::class, 'Subscription not found');

/**
 * @throws EndUserException
 * @throws \Random\RandomException
 */
it('deletes subscription on valid cancel token', function (): void {
    /** @var SubscriptionsDAO&\Mockery\MockInterface $subscriptionsDAO */
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    /** @var Feeds&\Mockery\MockInterface $feeds */
    $feeds = \Mockery::mock(Feeds::class);
    /** @var Newsletter&\Mockery\MockInterface $newsletter */
    $newsletter = \Mockery::mock(Newsletter::class);
    /** @var Auth&\Mockery\MockInterface $auth */
    $auth = \Mockery::mock(Auth::class);

    $feedUri = 'https://example.com/feed';
    $email = 'user@example.com';
    $token = 'valid-token';
    $subscription = new Subscription($feedUri, $email, true);

    $auth->shouldReceive('tokenKey')->once()->with('cancel', $feedUri, $email, '')->andReturn('cancel|' . $feedUri . '|' . $email . '|');
    $auth->shouldReceive('verify')->once()->with('cancel|' . $feedUri . '|' . $email . '|', $token)->andReturn(true);

    $subscriptionsDAO->shouldReceive('find')->once()->with($feedUri, $email)->andReturn($subscription);

    $subscriptionsDAO->shouldReceive('delete')->once()->with($subscription);

    $subs = new Subscriptions($subscriptionsDAO, $feeds, $newsletter, $auth);

    $subs->cancel($feedUri, $email, $token);
});

/**
 * @throws EndUserException
 * @throws \Random\RandomException
 */
it('throws on invalid cancel token', function (): void {
    /** @var SubscriptionsDAO&\Mockery\MockInterface $subscriptionsDAO */
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    /** @var Feeds&\Mockery\MockInterface $feeds */
    $feeds = \Mockery::mock(Feeds::class);
    /** @var Newsletter&\Mockery\MockInterface $newsletter */
    $newsletter = \Mockery::mock(Newsletter::class);
    /** @var Auth&\Mockery\MockInterface $auth */
    $auth = \Mockery::mock(Auth::class);

    $feedUri = 'https://example.com/feed';
    $email = 'user@example.com';
    $subscription = new Subscription($feedUri, $email, true);

    $subscriptionsDAO->shouldReceive('find')->once()->with($feedUri, $email)->andReturn($subscription);
    $auth->shouldReceive('tokenKey')->once()->with('cancel', $feedUri, $email, '')->andReturn('cancel|' . $feedUri . '|' . $email . '|');
    $auth->shouldReceive('verify')->once()->with('cancel|' . $feedUri . '|' . $email . '|', 'bad-token')->andReturn(false);
    $subscriptionsDAO->shouldNotReceive('delete');

    $subs = new Subscriptions($subscriptionsDAO, $feeds, $newsletter, $auth);

    $subs->cancel($feedUri, $email, 'bad-token');
})->throws(EndUserException::class, 'Invalid token');

/**
 * @throws EndUserException
 * @throws \Random\RandomException
 */
it('throws when subscription not found in cancel', function (): void {
    /** @var SubscriptionsDAO&\Mockery\MockInterface $subscriptionsDAO */
    $subscriptionsDAO = \Mockery::mock(SubscriptionsDAO::class);
    /** @var Feeds&\Mockery\MockInterface $feeds */
    $feeds = \Mockery::mock(Feeds::class);
    /** @var Newsletter&\Mockery\MockInterface $newsletter */
    $newsletter = \Mockery::mock(Newsletter::class);
    /** @var Auth&\Mockery\MockInterface $auth */
    $auth = \Mockery::mock(Auth::class);

    $feedUri = 'https://example.com/feed';
    $email = 'user@example.com';

    $subscriptionsDAO->shouldReceive('find')->once()->with($feedUri, $email)->andReturn(null);
    $auth->shouldNotReceive('verify');

    $subscriptionsDAO->shouldNotReceive('deactivate');

    $subs = new Subscriptions($subscriptionsDAO, $feeds, $newsletter, $auth);

    $subs->cancel($feedUri, $email, 'valid-token');
})->throws(EndUserException::class, 'Subscription not found');

