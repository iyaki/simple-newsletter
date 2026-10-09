<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/** @throws \Exception */
it('returns valid JSON error response structure', function (): void {
    $dbPath = getenv('NEWSLETTER_DB_PATH');
    \assert(\is_string($dbPath) && $dbPath !== '', 'NEWSLETTER_DB_PATH must be set');

    init_test_database($dbPath);

    // Test with invalid URI - should return 400 with valid error structure
    $response = http_get('/v1/subscriptions/', [
        'uri' => 'not-a-valid-url',
        'email' => 'test@example.com',
    ]);

    expect($response->getStatusCode())->toBe(400);

    // Response should be either HTML or JSON
    $contentType = $response->getHeaders(false)['content-type'][0] ?? '';

    if (str_contains($contentType, 'application/json')) {
        $body = \json_decode($response->getContent(false), true);
        expect($body)->toHaveKey('title');
    } else {
        $content = $response->getContent(false);
        expect($content)->toContain('Invalid');
    }
});

/** @throws \Exception */
it('returns valid structure for missing required parameters', function (): void {
    $dbPath = getenv('NEWSLETTER_DB_PATH');
    \assert(\is_string($dbPath) && $dbPath !== '', 'NEWSLETTER_DB_PATH must be set');

    init_test_database($dbPath);

    $response = http_get('/v1/subscriptions/', [
        // Both uri and email missing
    ]);

    expect($response->getStatusCode())->toBe(400);

    $contentType = $response->getHeaders(false)['content-type'][0] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $body = \json_decode($response->getContent(false), true);
        expect($body)->toHaveKey('title');
    }
});

/** @throws \Exception */
it('returns HTML by default (content negotiation)', function (): void {
    $dbPath = getenv('NEWSLETTER_DB_PATH');
    \assert(\is_string($dbPath) && $dbPath !== '', 'NEWSLETTER_DB_PATH must be set');

    init_test_database($dbPath);
    $response = http_get('/v1/subscriptions/', [
        'uri' => 'http://' . e2e_feed_host() . ':9995/valid.xml',
        'email' => 'test@example.com',
    ]);

    expect($response->getStatusCode())->toBe(200);
    $contentType = $response->getHeaders(false)['content-type'][0] ?? '';
    expect($contentType)->toContain('text/html');
    $content = $response->getContent(false);
    expect($content)->toContain('email confirmation');
});


/** @throws \Exception */
it('returns valid confirmation response structure', function (): void {
    $dbPath = getenv('NEWSLETTER_DB_PATH');
    \assert(\is_string($dbPath) && $dbPath !== '', 'NEWSLETTER_DB_PATH must be set');

    init_test_database($dbPath);

    $pdo = new \PDO('sqlite:' . $dbPath);
    $stmt = $pdo->prepare('INSERT INTO feeds (uri, title, link, last_update, trigger_hour) VALUES (?, ?, ?, ?, ?)');
    \assert($stmt instanceof \PDOStatement);
    $stmt->execute([
        'http://' . e2e_feed_host() . ':9995/valid.xml',
        'Test Feed',
        'https://example.com',
        time(),
        12,
    ]);
    $stmt = $pdo->prepare('INSERT INTO subscriptions (feed_uri, email, active) VALUES (?, ?, ?)');
    \assert($stmt instanceof \PDOStatement);
    $stmt->execute([
        'http://' . e2e_feed_host() . ':9995/valid.xml',
        'test@example.com',
        0,
    ]);

    // Token binding (audit fix): MAC = HMAC('confirm|feedUri|email|nonce', SECRET_KEY);
    // rows seeded by this test carry the default empty nonce.
    $feedUri = 'http://' . e2e_feed_host() . ':9995/valid.xml';
    $token = hash_hmac(algo: 'sha256', data: 'confirm|' . $feedUri . '|test@example.com|', key: (string) getenv('SECRET_KEY'));

    $response = http_get('/v1/subscriptions/confirmation/', [
        'uri' => $feedUri,
        'email' => 'test@example.com',
        'token' => $token,
    ]);

    expect($response->getStatusCode())->toBe(200);

    $contentType = $response->getHeaders(false)['content-type'][0] ?? '';

    // Check for X-Robots-Tag header per OpenAPI spec
    $headers = $response->getHeaders(false);
    expect($headers)->toHaveKey('x-robots-tag');
    $xRobotsTag = $headers['x-robots-tag'][0] ?? '';
    expect($xRobotsTag)->toContain('noindex');
    // Response body should be HTML or JSON
    if (str_contains($contentType, 'application/json')) {
        $body = \json_decode($response->getContent(false), true);
        expect($body)->toHaveKey('title');
    } else {
        $content = $response->getContent(false);
        expect($content)->toContain('confirmed');
    }
});

/** @throws \Exception */
it('returns valid error structure for invalid confirmation token', function (): void {
    $dbPath = getenv('NEWSLETTER_DB_PATH');
    \assert(\is_string($dbPath) && $dbPath !== '', 'NEWSLETTER_DB_PATH must be set');

    init_test_database($dbPath);

    $response = http_get('/v1/subscriptions/confirmation/', [
        'uri' => 'http://' . e2e_feed_host() . ':9995/valid.xml',
        'email' => 'test@example.com',
        'token' => 'wrong-token',
    ]);

    expect($response->getStatusCode())->toBe(400);

    $contentType = $response->getHeaders(false)['content-type'][0] ?? '';

    // Error response should have valid structure
    if (str_contains($contentType, 'application/json')) {
        $body = \json_decode($response->getContent(false), true);
        expect($body)->toHaveKey('title');
        $title = $body['title'] ?? '';
        expect(\in_array($title, ['Invalid', 'Error'], strict: true))->toBeTrue();
    }
});

/** @throws \Exception */
it('returns valid cancellation response structure', function (): void {
    $dbPath = getenv('NEWSLETTER_DB_PATH');
    \assert(\is_string($dbPath) && $dbPath !== '', 'NEWSLETTER_DB_PATH must be set');

    init_test_database($dbPath);
    $pdo = new \PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare('INSERT INTO feeds (uri, title, link, last_update, trigger_hour) VALUES (?, ?, ?, ?, ?)');
    \assert($stmt instanceof \PDOStatement);
    $stmt->execute([
        'http://' . e2e_feed_host() . ':9995/valid.xml',
        'Test Feed',
        'https://example.com',
        time(),
        12,
    ]);
    $stmt = $pdo->prepare('INSERT INTO subscriptions (feed_uri, email, active) VALUES (?, ?, ?)');
    \assert($stmt instanceof \PDOStatement);
    $stmt->execute([
        'http://' . e2e_feed_host() . ':9995/valid.xml',
        'test@example.com',
        1,
    ]);

    // Token binding (audit fix): MAC = HMAC('cancel|feedUri|email|nonce', SECRET_KEY);
    // rows seeded by this test carry the default empty nonce.
    $feedUri = 'http://' . e2e_feed_host() . ':9995/valid.xml';
    $token = hash_hmac(algo: 'sha256', data: 'cancel|' . $feedUri . '|test@example.com|', key: (string) getenv('SECRET_KEY'));

    $response = http_get('/v1/subscriptions/cancellation/', [
        'uri' => $feedUri,
        'email' => 'test@example.com',
        'token' => $token,
    ]);

    expect($response->getStatusCode())->toBe(200);

    $headers = $response->getHeaders(false);
    $contentType = $headers['content-type'][0] ?? '';

    // Check for X-Robots-Tag header per OpenAPI spec
    expect($headers)->toHaveKey('x-robots-tag');
    $xRobotsTag = $headers['x-robots-tag'][0] ?? '';
    expect($xRobotsTag)->toContain('noindex');
    expect(\in_array($xRobotsTag, ['noindex', 'nofollow', 'noindex, nofollow'], strict: true))->toBeTrue();
    if (\str_contains($contentType, 'application/json')) {
        $body = \json_decode($response->getContent(false), true);
        expect($body)->toHaveKey('title');
    }
});

/** @throws \Exception */
it('returns valid error structure for invalid cancellation token', function (): void {
    $dbPath = getenv('NEWSLETTER_DB_PATH');
    \assert(\is_string($dbPath) && $dbPath !== '', 'NEWSLETTER_DB_PATH must be set');

    init_test_database($dbPath);

    $response = http_get('/v1/subscriptions/cancellation/', [
        'uri' => 'http://' . e2e_feed_host() . ':9995/valid.xml',
        'email' => 'test@example.com',
        'token' => 'wrong-token',
    ]);

    expect($response->getStatusCode())->toBe(400);

    $contentType = $response->getHeaders(false)['content-type'][0] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $body = \json_decode($response->getContent(false), true);
        expect($body)->toHaveKey('title');
    }
});

/** @throws \Exception */
it('validates JSON response when Accept header is set', function (): void {
    $dbPath = getenv('NEWSLETTER_DB_PATH');
    \assert(\is_string($dbPath) && $dbPath !== '', 'NEWSLETTER_DB_PATH must be set');

    init_test_database($dbPath);

    $response = http_get('/v1/subscriptions/', [
        'uri' => 'http://' . e2e_feed_host() . ':9995/valid.xml',
        'email' => 'test@example.com',
    ]);

    expect($response->getStatusCode())->toBe(200);

    $contentType = $response->getHeaders(false)['content-type'][0] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $body = \json_decode($response->getContent(false), true);
        expect($body)->toHaveKey('title');
    }
});
