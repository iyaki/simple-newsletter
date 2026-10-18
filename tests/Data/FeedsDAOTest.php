<?php

declare(strict_types=1);

use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\FeedMetadata;
use SimpleNewsletter\Data\FeedsDAO;
covers(SimpleNewsletter\Data\FeedsDAO::class);

/** @var FeedsDAO|null $dao */
$dao = null;
beforeEach(function () use (&$db, &$dao): void {
    /**
     * @throws \PDOException
     * @throws \RuntimeException
     */
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
    $dao = new FeedsDAO($db);
});

test('find returns null for missing URI', function () use (&$dao): void {
    \assert($dao instanceof FeedsDAO);
    expect($dao->find('https://example.com/missing'))->toBeNull();
});

test('new inserts and finds retrieves a feed', function () use (&$dao): void {
    \assert($dao instanceof FeedsDAO);
    /**
     * @throws \SimpleNewsletter\Components\EndUserException
     */
    $metadata = new FeedMetadata(
        'https://example.com/feed',
        'Test Feed',
        'https://example.com',
        new \DateTimeImmutable(),
    );
    $feed = new Feed($metadata);
    $dao->new($feed);
    $found = $dao->find('https://example.com/feed');
    \assert($found instanceof Feed);
    expect($found->getTitle())->toEqual('Test Feed');
});

test('update modifies feed fields', function () use (&$dao): void {
    \assert($dao instanceof FeedsDAO);
    /**
     * @throws \SimpleNewsletter\Components\EndUserException
     */
    $metadata = new FeedMetadata(
        'https://example.com/feed',
        'Original',
        'https://example.com',
        new \DateTimeImmutable(),
    );
    $feed = new Feed($metadata);
    $dao->new($feed);
    $updatedMetadata = new FeedMetadata(
        'https://example.com/feed',
        'Updated Title',
        'https://example.com',
        new \DateTimeImmutable(),
    );
    $updated = new Feed(metadata: $updatedMetadata, lastSentPostUri: 'https://example.com/last-post');
    $dao->update($updated);
    $found = $dao->find('https://example.com/feed');
    \assert($found instanceof Feed);
    expect($found->getTitle())->toEqual('Updated Title');
    expect($found->getLastSentPostUri())->toEqual('https://example.com/last-post');
});

test('getScheduled returns feeds for matching trigger hour with active subscriptions', function () use (
    &$db,
    &$dao,
): void {
    \assert($dao instanceof FeedsDAO);
    \assert($db instanceof \PDO);
    // Insert feed directly with trigger_hour
    $stmt = $db->prepare('INSERT INTO feeds (uri, title, link, last_update, trigger_hour) VALUES (?, ?, ?, ?, ?)');
    \assert($stmt instanceof \PDOStatement, 'stmt should be prepared');
    $stmt->execute([
        'https://scheduled.example.com/feed',
        'Scheduled',
        'https://scheduled.example.com',
        \time(),
        12,
    ]);
    $subStmt = $db->prepare('INSERT INTO subscriptions (feed_uri, email, active) VALUES (?, ?, ?)');
    \assert($subStmt instanceof \PDOStatement, 'subStmt should be prepared');
    $subStmt->execute(['https://scheduled.example.com/feed', 'subscriber@example.com', 1]);
    $results = $dao->getScheduled(new \DateTimeImmutable('2024-01-01 12:00:00'), 24, \time() - 86400);
    \assert($results[0] !== null, 'first result should exist');
    expect($results[0]->metadata->uri)->toEqual('https://scheduled.example.com/feed');
});

test('getScheduled returns empty array when no feeds match', function () use (&$dao): void {
    \assert($dao instanceof FeedsDAO);
    /** @var array<int, \SimpleNewsletter\Data\Feed> $results */
    $results = $dao->getScheduled(new \DateTimeImmutable('2024-01-01 03:00:00'), 24, \time() - 86_400);
    expect($results)->toBeEmpty();
});

test('recordFailedFetch counts consecutive failures and resetFetchFailures clears them', function () use (&$db, &$dao): void {
    \assert($dao instanceof FeedsDAO);
    \assert($db instanceof \PDO);
    $stmt = $db->prepare('INSERT INTO feeds (uri, title, link, last_update, trigger_hour) VALUES (?, ?, ?, ?, ?)');
    \assert($stmt instanceof \PDOStatement, 'stmt should be prepared');
    $stmt->execute(['https://example.com/feed', 'Feed', 'https://example.com', \time(), 3]);

    expect($dao->recordFailedFetch('https://example.com/feed', new \DateTimeImmutable()))->toBe(1)
        ->and($dao->recordFailedFetch('https://example.com/feed', new \DateTimeImmutable()))->toBe(2)
        ->and($dao->recordFailedFetch('https://example.com/feed', new \DateTimeImmutable()))->toBe(3);

    $dao->resetFetchFailures('https://example.com/feed');
    expect($dao->recordFailedFetch('https://example.com/feed', new \DateTimeImmutable()))->toBe(1);
});

test('getScheduled hides freshly quarantined feeds but lets stale ones retry', function () use (&$db, &$dao): void {
    \assert($dao instanceof FeedsDAO);
    \assert($db instanceof \PDO);
    $datetime = new \DateTimeImmutable('2024-01-01 05:00:00');
    $now = $datetime->getTimestamp();
    $hour = (int) $datetime->format('H');
    $insert = $db->prepare('INSERT INTO feeds (uri, title, link, last_update, trigger_hour, consecutive_failures, last_fetch_attempt) VALUES (?, ?, ?, ?, ?, ?, ?)');
    \assert($insert instanceof \PDOStatement, 'insert should be prepared');
    // healthy feed
    $insert->execute(['https://a.example.com/feed', 'A', 'https://a.example.com', $now, $hour, 0, null]);
    // quarantined feed, last attempt 1h ago: must stay hidden
    $insert->execute(['https://b.example.com/feed', 'B', 'https://b.example.com', $now, $hour, 24, $now - 3_600]);
    // quarantined feed, last attempt 25h ago: due for the daily retry probe
    $insert->execute(['https://c.example.com/feed', 'C', 'https://c.example.com', $now, $hour, 30, $now - 90_000]);
    // just under the threshold: never hidden
    $insert->execute(['https://d.example.com/feed', 'D', 'https://d.example.com', $now, $hour, 23, $now - 60]);

    $subStmt = $db->prepare('INSERT INTO subscriptions (feed_uri, email, active) VALUES (?, ?, ?)');
    \assert($subStmt instanceof \PDOStatement, 'subStmt should be prepared');
    foreach (['a', 'b', 'c', 'd'] as $slug) {
        $subStmt->execute(["https://{$slug}.example.com/feed", 'subscriber@example.com', 1]);
    }

    $results = $dao->getScheduled($datetime, 24, $now - 86_400);
    $uris = \array_map(static fn (Feed $feed): string => $feed->metadata->uri, $results);

    expect($uris)->toEqual([
        'https://a.example.com/feed',
        'https://c.example.com/feed',
        'https://d.example.com/feed',
    ]);
});


