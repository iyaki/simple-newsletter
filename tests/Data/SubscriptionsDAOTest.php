<?php

declare(strict_types=1);

use SimpleNewsletter\Data\SubscriptionsDAO;
use SimpleNewsletter\Data\Subscription;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\FeedsDAO;

beforeEach(function () {
    $this->db = new \PDO('sqlite::memory:');
    $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    foreach (glob(__DIR__ . '/../../migrations/*.sql') as $migration) {
        $this->db->exec(\file_get_contents($migration));
    }
    $this->dao = new SubscriptionsDAO($this->db);
    // Seed a feed (FK constraint)
    $feedsDao = new FeedsDAO($this->db);
    $feedsDao->new(new Feed('https://example.com/feed', 'Test', 'https://example.com', new \DateTimeImmutable()));
});

test('new inserts and find retrieves a subscription', function () {
    $this->dao->new(new Subscription('https://example.com/feed', 'user@example.com'));
    $found = $this->dao->find('https://example.com/feed', 'user@example.com');
    expect($found)->not->toBeNull();
    expect($found->email)->toEqual('user@example.com');
});

test('find returns null for non-existent subscription', function () {
    $result = $this->dao->find('https://example.com/feed', 'nonexistent@example.com');
    expect($result)->toBeNull();
});

test('activate sets active flag', function () {
    $sub = new Subscription('https://example.com/feed', 'user@example.com');
    $this->dao->new($sub);
    $this->dao->activate($sub);
    $found = $this->dao->find('https://example.com/feed', 'user@example.com');
    expect($found->active)->toBeTrue();
});

test('deactivate clears active flag', function () {
    $sub = new Subscription('https://example.com/feed', 'user@example.com');
    $this->dao->new($sub);
    $this->dao->activate($sub);
    $this->dao->deactivate($sub);
    $found = $this->dao->find('https://example.com/feed', 'user@example.com');
    expect($found->active)->toBeFalse();
});

test('findActiveSubscriptionsFor returns only active subs', function () {
    $feed = new Feed('https://example.com/feed', 'Test', 'https://example.com', new \DateTimeImmutable());
    $active = new Subscription('https://example.com/feed', 'active@example.com');
    $inactive = new Subscription('https://example.com/feed', 'inactive@example.com');
    $this->dao->new($active);
    $this->dao->new($inactive);
    $this->dao->activate($active);
    $results = $this->dao->findActiveSubscriptionsFor($feed);
    expect($results)->toHaveCount(1);
    expect($results[0]->email)->toEqual('active@example.com');
});

test('findActiveSubscriptionsFor returns empty array for feed with no active subscriptions', function () {
    $feed = new Feed('https://example.com/feed', 'Test', 'https://example.com', new \DateTimeImmutable());
    // No subscriptions added for this feed
    $results = $this->dao->findActiveSubscriptionsFor($feed);
    expect($results)->toBeEmpty();
});
