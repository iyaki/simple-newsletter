<?php

declare(strict_types=1);

use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\FeedMetadata;
use SimpleNewsletter\Data\FeedsDAO;
covers(SimpleNewsletter\Data\SubscriptionsDAO::class);
use SimpleNewsletter\Data\Subscription;
use SimpleNewsletter\Data\SubscriptionsDAO;

/** @var SubscriptionsDAO|null $dao */
$dao = null;

/** @var \PDO|null $pdo */
$pdo = null;

/**
 * @throws \SimpleNewsletter\Components\EndUserException
 * @throws \Random\RandomException
 * @throws \PDOException
 * @throws \RuntimeException
 */
beforeEach(function () use (&$dao, &$pdo): void {
    try {
        $db = new \PDO('sqlite::memory:');
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        require_once __DIR__ . '/../migrations.php';
        apply_migrations($db);
        $dao = new SubscriptionsDAO($db);
        $pdo = $db;
        // Seed a feed (FK constraint)
        $feedsDao = new FeedsDAO($db);
        $metadata = new FeedMetadata(
            'https://example.com/feed',
            'Test',
            'https://example.com',
            new \DateTimeImmutable(),
        );
        try {
            $feedsDao->new(new Feed($metadata));
        } catch (\SimpleNewsletter\Components\EndUserException $e) {
            throw $e;
        }
    } catch (\PDOException $e) {
        throw $e;
    } catch (\RuntimeException $e) {
        throw $e;
    } catch (\Random\RandomException $e) {
        throw $e;
    }
});

/**
 * @throws \SimpleNewsletter\Components\EndUserException
 * @throws \Random\RandomException
 */
test('new inserts and find retrieves a subscription', function () use (&$dao): void {
    \assert($dao instanceof SubscriptionsDAO, 'dao should be initialized');
    $dao->new(new Subscription('https://example.com/feed', 'user@example.com'));
    $found = $dao->find('https://example.com/feed', 'user@example.com');
    \assert($found instanceof Subscription, 'found should be a Subscription');
    expect($found->email)->toEqual('user@example.com');
});

/**
 * @throws \SimpleNewsletter\Components\EndUserException
 * @throws \Random\RandomException
 */
test('find returns null for non-existent subscription', function () use (&$dao): void {
    \assert($dao instanceof SubscriptionsDAO, 'dao should be initialized');
    $result = $dao->find('https://example.com/feed', 'nonexistent@example.com');
    expect($result)->toBeNull();
});

/**
 * @throws \SimpleNewsletter\Components\EndUserException
 * @throws \Random\RandomException
 */
test('activate sets active flag', function () use (&$dao): void {
    \assert($dao instanceof SubscriptionsDAO, 'dao should be initialized');
    $sub = new Subscription('https://example.com/feed', 'user@example.com');
    $dao->new($sub);
    $dao->activate($sub);
    $found = $dao->find('https://example.com/feed', 'user@example.com');
    \assert($found instanceof Subscription, 'found should be a Subscription');
    expect($found->active)->toBeTrue();
});

/**
 * @throws \SimpleNewsletter\Components\EndUserException
 * @throws \Random\RandomException
 */
test('delete removes subscription from database', function () use (&$dao): void {
    \assert($dao instanceof SubscriptionsDAO, 'dao should be initialized');
    $sub = new Subscription('https://example.com/feed', 'user@example.com');
    $dao->new($sub);
    $dao->activate($sub);
    $dao->delete($sub);
    $found = $dao->find('https://example.com/feed', 'user@example.com');
    expect($found)->toBeNull();
});

/**
 * @throws \SimpleNewsletter\Components\EndUserException
 * @throws \Random\RandomException
 */
test('findActiveSubscriptionsFor returns only active subs', function () use (&$dao): void {
    \assert($dao instanceof SubscriptionsDAO, 'dao should be initialized');
    $metadata = new FeedMetadata('https://example.com/feed', 'Test', 'https://example.com', new \DateTimeImmutable());
    $feed = new Feed($metadata);
    $active = new Subscription('https://example.com/feed', 'active@example.com');
    $inactive = new Subscription('https://example.com/feed', 'inactive@example.com');
    $dao->new($active);
    $dao->new($inactive);
    $dao->activate($active);
    $results = $dao->findActiveSubscriptionsFor($feed);
    expect($results)->toHaveCount(1);
    \assert($results[0] !== null, 'first result should exist');
    expect($results[0]->email)->toEqual('active@example.com');
});

/**
 * @throws \SimpleNewsletter\Components\EndUserException
 * @throws \Random\RandomException
 */
test('findActiveSubscriptionsFor returns empty array for feed with no active subscriptions', function () use (
    &$dao,
): void {
    \assert($dao instanceof SubscriptionsDAO, 'dao should be initialized');
    $metadata = new FeedMetadata('https://example.com/feed', 'Test', 'https://example.com', new \DateTimeImmutable());
    $feed = new Feed($metadata);
    // No subscriptions added for this feed
    $results = $dao->findActiveSubscriptionsFor($feed);
    expect($results)->toBeEmpty();
});

/**
 * @throws \SimpleNewsletter\Components\EndUserException
 * @throws \Random\RandomException
 */
test('markConfirmationSent claims the resend slot once per interval', function () use (&$dao, &$pdo): void {
    \assert($dao instanceof SubscriptionsDAO, 'dao should be initialized');
    $subscription = new Subscription('https://example.com/feed', 'throttle@example.com');
    $dao->new($subscription);

    // New rows carry sent-at 0: the first claim always succeeds.
    expect($dao->markConfirmationSent($subscription, 3600))->toBeTrue();

    // A second claim inside the window is rejected (email-bombing throttle).
    expect($dao->markConfirmationSent($subscription, 3600))->toBeFalse();

    // Backdate the last send past the cutoff: the slot is claimable again.
    \assert($pdo instanceof \PDO, 'pdo should be initialized');
    /** @var \PDOStatement|false $backdate */
    $backdate = $pdo->prepare('UPDATE subscriptions SET confirmation_sent_at = ? WHERE feed_uri = ? AND email = ?');
    \assert($backdate instanceof \PDOStatement, 'backdate statement should prepare');
    $backdate->execute([\time() - 3600, 'https://example.com/feed', 'throttle@example.com']);

    expect($dao->markConfirmationSent($subscription, 3600))->toBeTrue();
});


