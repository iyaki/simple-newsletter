<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Tests\E2e\DatabaseCleaner;
use Tests\E2e\HttpClientHelpers;

uses(HttpClientHelpers::class, DatabaseCleaner::class);

beforeEach(function () {
    $this->cleanDatabase();
    initTestDatabase(\getenv('NEWSLETTER_DB_PATH'));

    // Create active subscription with feed
    $pdo = new \PDO('sqlite:' . getenv('NEWSLETTER_DB_PATH'));
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $pdo->prepare('INSERT INTO feeds (uri, title, link, last_update, trigger_hour) VALUES (?, ?, ?, ?, ?)')->execute([
        'https://example.com/feed.xml',
        'Test Feed',
        'https://example.com',
        time(),
        12,
    ]);
    $pdo->prepare('INSERT INTO subscriptions (feed_uri, email, active) VALUES (?, ?, ?)')->execute([
        'https://example.com/feed.xml',
        'test@example.com',
        1,
    ]);
});

it('cancels subscription with valid token', function () {
    $token = hash_hmac('sha256', 'test@example.com', getenv('SECRET_KEY'));

    $response = self::get('/v1/subscriptions/cancellation/', [
        'feed_uri' => 'https://example.com/feed.xml',
        'email' => 'test@example.com',
        'token' => $token,
    ]);

    expect($response->getStatusCode())->toBe(200);

    // Verify subscription is inactive
    $pdo = new \PDO('sqlite:' . getenv('NEWSLETTER_DB_PATH'));
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare('SELECT active FROM subscriptions WHERE feed_uri = ? AND email = ?');
    $stmt->execute(['https://example.com/feed.xml', 'test@example.com']);
    $sub = $stmt->fetch(\PDO::FETCH_ASSOC);
    expect($sub['active'])->toBe(0);
});

it('rejects invalid cancellation token', function () {
    $response = self::get('/v1/subscriptions/cancellation/', [
        'feed_uri' => 'https://example.com/feed.xml',
        'email' => 'test@example.com',
        'token' => 'invalid-token',
    ]);

    expect($response->getStatusCode())->toBe(400);
});
