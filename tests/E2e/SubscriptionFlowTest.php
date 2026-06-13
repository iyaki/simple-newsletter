<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Tests\E2e\HttpClientHelpers;
use Tests\E2e\DatabaseCleaner;

uses(HttpClientHelpers::class, DatabaseCleaner::class);

beforeEach(function () {
    $this->cleanDatabase();
    initTestDatabase(\getenv('NEWSLETTER_DB_PATH'));
});

it('completes subscription flow end-to-end', function () {
    // 1. Initial subscription request
    $response = self::get('/v1/subscriptions/', [
        'uri' => 'https://example.com/feed.xml',
        'email' => 'test@example.com',
    ]);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getHeaders()['content-type'][0])->toContain('text/html');

    $content = $response->getContent();
    expect($content)->toContain('email confirmation');

    // 2. Verify subscription created in DB (unconfirmed)
    $pdo = new \PDO('sqlite:' . getenv('NEWSLETTER_DB_PATH'));
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare('SELECT * FROM subscriptions WHERE feed_uri = ? AND email = ?');
    $stmt->execute(['https://example.com/feed.xml', 'test@example.com']);
    $sub = $stmt->fetch(\PDO::FETCH_ASSOC);
    expect($sub)->not->toBeNull();
    expect($sub['active'])->toBe(0); // Unconfirmed

    // 3. Generate confirmation token
    $token = hash_hmac('sha256', 'test@example.com', getenv('SECRET_KEY'));

    // 4. Confirm subscription
    $confirmResponse = self::get('/v1/subscriptions/confirmation/', [
        'feed_uri' => 'https://example.com/feed.xml',
        'email' => 'test@example.com',
        'token' => $token,
    ]);

    expect($confirmResponse->getStatusCode())->toBe(200);

    // 5. Verify subscription active in DB
    $stmt->execute(['https://example.com/feed.xml', 'test@example.com']);
    $confirmedSub = $stmt->fetch(\PDO::FETCH_ASSOC);
    expect($confirmedSub['active'])->toBe(1);
});

it('rejects invalid feed URI', function () {
    $response = self::get('/v1/subscriptions/', [
        'uri' => 'not-a-valid-url',
        'email' => 'test@example.com',
    ]);

    expect($response->getStatusCode())->toBe(400);
    $content = $response->getContent();
    expect($content)->toContain('Invalid');
});

it('rejects missing required fields', function () {
    $response = self::get('/v1/subscriptions/', [
        'uri' => 'https://example.com/feed.xml',
        // email missing
    ]);

    expect($response->getStatusCode())->toBe(400);
});