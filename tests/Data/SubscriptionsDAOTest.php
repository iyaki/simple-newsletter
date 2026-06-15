<?php

declare(strict_types=1);

use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\FeedMetadata;
use SimpleNewsletter\Data\FeedsDAO;
use SimpleNewsletter\Data\Subscription;
use SimpleNewsletter\Data\SubscriptionsDAO;

/** @var SubscriptionsDAO|null $dao */
$dao = null;

/**
 * @throws \PDOException
 * @throws \RuntimeException
 * @throws \Random\RandomException
 * @throws \SimpleNewsletter\Components\EndUserException
 */
beforeEach(function () use (&$dao): void {
    $db = new \PDO('sqlite::memory:');
    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $migrationFiles = \glob(__DIR__ . '/../../migrations/*.sql');
    if ($migrationFiles === false) {
        throw new \RuntimeException('Failed to read migration files');
    }
    foreach ($migrationFiles as $migration) {
        $sql = \file_get_contents($migration);
        if ($sql === false) {
            continue;
        }
        $db->exec($sql);
    }
    $dao = new SubscriptionsDAO($db);
    // Seed a feed (FK constraint)
    $feedsDao = new FeedsDAO($db);
    $metadata = new FeedMetadata('https://example.com/feed', 'Test', 'https://example.com', new \DateTimeImmutable());
    $feedsDao->new(new Feed($metadata));
});

/** @throws \SimpleNewsletter\Components\EndUserException */
test('new inserts and find retrieves a subscription', function () use (&$dao): void {
    $dao->new(new Subscription('https://example.com/feed', 'user@example.com'));
    $found = $dao->find('https://example.com/feed', 'user@example.com');
    \assert($found instanceof Subscription);
    expect($found->email)->toEqual('user@example.com');
});

/** @throws \SimpleNewsletter\Components\EndUserException */
test('find returns null for non-existent subscription', function () use (&$dao): void {
    $result = $dao->find('https://example.com/feed', 'nonexistent@example.com');
    expect($result)->toBeNull();
});

/** @throws \SimpleNewsletter\Components\EndUserException */
test('activate sets active flag', function () use (&$dao): void {
    $sub = new Subscription('https://example.com/feed', 'user@example.com');
    $dao->new($sub);
    $dao->activate($sub);
    $found = $dao->find('https://example.com/feed', 'user@example.com');
    \assert($found instanceof Subscription);
    expect($found->active)->toBeTrue();
});

/** @throws \SimpleNewsletter\Components\EndUserException */
test('deactivate clears active flag', function () use (&$dao): void {
    $sub = new Subscription('https://example.com/feed', 'user@example.com');
    $dao->new($sub);
    $dao->activate($sub);
    $dao->deactivate($sub);
    $found = $dao->find('https://example.com/feed', 'user@example.com');
    \assert($found instanceof Subscription);
    expect($found->active)->toBeFalse();
});

/**
 * @throws \SimpleNewsletter\Components\EndUserException
 * @throws \Random\RandomException
 */
test('findActiveSubscriptionsFor returns only active subs', function () use (&$dao): void {
    $metadata = new FeedMetadata('https://example.com/feed', 'Test', 'https://example.com', new \DateTimeImmutable());
    $feed = new Feed($metadata);
    $active = new Subscription('https://example.com/feed', 'active@example.com');
    $inactive = new Subscription('https://example.com/feed', 'inactive@example.com');
    $dao->new($active);
    $dao->new($inactive);
    $dao->activate($active);
    $results = $dao->findActiveSubscriptionsFor($feed);
    expect($results)->toHaveCount(1);
    \assert(isset($results[0]));
    expect($results[0]->email)->toEqual('active@example.com');
});

/**
 * @throws \SimpleNewsletter\Components\EndUserException
 * @throws \Random\RandomException
 */
test('findActiveSubscriptionsFor returns empty array for feed with no active subscriptions', function () use (&$dao): void {
    $metadata = new FeedMetadata('https://example.com/feed', 'Test', 'https://example.com', new \DateTimeImmutable());
    $feed = new Feed($metadata);
    // No subscriptions added for this feed
    $results = $dao->findActiveSubscriptionsFor($feed);
    expect($results)->toBeEmpty();
});
