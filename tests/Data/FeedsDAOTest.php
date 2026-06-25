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
    expect($dao->find('https://example.com/missing'))->toBeNull();
});

test('new inserts and finds retrieves a feed', function () use (&$dao): void {
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
    expect($found)->toBeInstanceOf(Feed::class);
    expect($found->getTitle())->toEqual('Test Feed');
});

test('update modifies feed fields', function () use (&$dao): void {
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
    expect($found)->toBeInstanceOf(Feed::class);
    expect($found->getTitle())->toEqual('Updated Title');
    expect($found->getLastSentPostUri())->toEqual('https://example.com/last-post');
});

test('getScheduled returns feeds for matching trigger hour with active subscriptions', function () use (
    &$db,
    &$dao,
): void {
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
    $results = $dao->getScheduled(new \DateTimeImmutable('2024-01-01 12:00:00'));
    \assert($results[0] !== null, 'first result should exist');
    expect($results[0]->metadata->uri)->toEqual('https://scheduled.example.com/feed');
});

test('getScheduled returns empty array when no feeds match', function () use (&$dao): void {
    /** @var array<int, \SimpleNewsletter\Data\Feed> $results */
    $results = $dao->getScheduled(new \DateTimeImmutable('2024-01-01 03:00:00'));
    expect($results)->toBeEmpty();
});


