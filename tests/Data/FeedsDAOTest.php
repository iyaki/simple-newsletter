<?php

declare(strict_types=1);

use SimpleNewsletter\Data\FeedsDAO;
use SimpleNewsletter\Data\Feed;

beforeEach(function () {
    $this->db = new \PDO('sqlite::memory:');
    $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    // Run all migrations against in-memory DB
    foreach (glob(__DIR__ . '/../../migrations/*.sql') as $migration) {
        $this->db->exec(\file_get_contents($migration));
    }
    $this->dao = new FeedsDAO($this->db);
});

test('find returns null for missing URI', function () {
    expect($this->dao->find('https://example.com/missing'))->toBeNull();
});

test('new inserts and find retrieves a feed', function () {
    $feed = new Feed('https://example.com/feed', 'Test Feed', 'https://example.com', new \DateTimeImmutable());
    $this->dao->new($feed);
    $found = $this->dao->find('https://example.com/feed');
    expect($found)->not->toBeNull();
    expect($found->title)->toEqual('Test Feed');
});

test('update modifies feed fields', function () {
    $feed = new Feed('https://example.com/feed', 'Original', 'https://example.com', new \DateTimeImmutable());
    $this->dao->new($feed);
    $updated = new Feed('https://example.com/feed', 'Updated Title', 'https://example.com', new \DateTimeImmutable(), 'https://example.com/last-post');
    $this->dao->update($updated);
    $found = $this->dao->find('https://example.com/feed');
    expect($found->title)->toEqual('Updated Title');
    expect($found->lastSentPostUri)->toEqual('https://example.com/last-post');
});

test('getScheduled returns feeds for matching trigger hour with active subscriptions', function () {
    // Insert feeds with known trigger_hour (cannot use FeedsDAO::new() which uses rand())
    $this->db->exec("INSERT INTO feeds (uri, title, link, last_update, trigger_hour) VALUES ('https://example.com/morning', 'Morning Feed', 'https://example.com', 0, 10)");
    $this->db->exec("INSERT INTO feeds (uri, title, link, last_update, trigger_hour) VALUES ('https://example.com/afternoon', 'Afternoon Feed', 'https://example.com', 0, 14)");

    // Insert active subscription for morning feed
    $this->db->exec("INSERT INTO subscriptions (feed_uri, email, active) VALUES ('https://example.com/morning', 'user@example.com', 1)");

    // Get scheduled feeds for hour 10
    $results = $this->dao->getScheduled(new \DateTimeImmutable('2024-01-01 10:00:00'));

    expect($results)->toHaveCount(1);
    expect($results[0]->uri)->toEqual('https://example.com/morning');
});

test('getScheduled returns empty array when no feeds match', function () {
    $results = $this->dao->getScheduled(new \DateTimeImmutable('2024-01-01 03:00:00'));
    expect($results)->toBeEmpty();
});
