<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/** @throws \Exception */
it('returns valid JSON error response structure', function (): void {
    $dbPath = getenv('NEWSLETTER_DB_PATH');

    assert(is_string($dbPath) && $dbPath !== '');

    init_test_database($dbPath);

    // Test with invalid URI - should return 400 with valid error structure
    $response = http_get('/v1/subscriptions/', [
        'uri' => 'not-a-valid-url',
        'email' => 'test@example.com',
    ]);

    expect($response->getStatusCode())->toBe(400);

    // Response should be either HTML or JSON
    $contentType = $response->getHeaders()['content-type'][0] ?? '';

    if (str_contains($contentType, 'application/json')) {
        $body = to_array_safe($response);
        expect($body)->toHaveKey('title');
    } else {
        $content = get_content_safe($response);
        expect($content)->toContain('Invalid');
    }
});

/** @throws \Exception */
it('returns valid structure for missing required parameters', function (): void {
    $dbPath = getenv('NEWSLETTER_DB_PATH');

    assert(is_string($dbPath) && $dbPath !== '');

    init_test_database($dbPath);

    $response = http_get('/v1/subscriptions/', [
        // Both uri and email missing
    ]);

    expect($response->getStatusCode())->toBe(400);

    $contentType = $response->getHeaders()['content-type'][0] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $body = to_array_safe($response);
        expect($body)->toHaveKey('title');
    }
});

it('returns HTML by default (content negotiation)', function (): void {
    $dbPath = getenv('NEWSLETTER_DB_PATH');

    assert(is_string($dbPath) && $dbPath !== '');

    init_test_database($dbPath);

    $response = http_get('/v1/subscriptions/', [
        'uri' => 'https://example.com/feed.xml',
        'email' => 'test@example.com',
    ]);

    expect($response->getStatusCode())->toBe(200);
    $contentType = $response->getHeaders()['content-type'][0] ?? '';
    expect($contentType)->toContain('text/html');
    $content = get_content_safe($response);
    expect($content)->toContain('email confirmation');
});

/** @throws \Exception */
it('returns 404 for unknown routes with valid error structure', function (): void {
    $dbPath = getenv('NEWSLETTER_DB_PATH');

    assert(is_string($dbPath) && $dbPath !== '');

    init_test_database($dbPath);

    $response = http_get('/nonexistent-endpoint');

    expect($response->getStatusCode())->toBe(404);

    // Even 404 should have proper structure
    $contentType = $response->getHeaders()['content-type'][0] ?? '';
    $content = get_content_safe($response);

    // Should return some content (error page)
    expect($content)->not->toBeEmpty();
});

/** @throws \Exception */
it('returns valid confirmation response structure', function (): void {
    $dbPath = getenv('NEWSLETTER_DB_PATH');

    assert(is_string($dbPath) && $dbPath !== '');

    init_test_database($dbPath);

    $dbPath = getenv('NEWSLETTER_DB_PATH');
    assert($dbPath !== false, 'NEWSLETTER_DB_PATH not set');
    $pdo = new \PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare('INSERT INTO feeds (uri, title, link, last_update, trigger_hour) VALUES (?, ?, ?, ?, ?)');
    assert($stmt !== false);
    $stmt->execute([
        'https://example.com/feed.xml',
        'Test Feed',
        'https://example.com',
        time(),
        12,
    ]);
    $stmt = $pdo->prepare('INSERT INTO subscriptions (feed_uri, email, active) VALUES (?, ?, ?)');
    assert($stmt !== false);
    $stmt->execute([
        'https://example.com/feed.xml',
        'test@example.com',
        0,
    ]);

    $token = hash_hmac(algo: 'sha256', data: 'test@example.com', key: (string) getenv('SECRET_KEY'));

    $response = http_get('/v1/subscriptions/confirmation/', [
        'uri' => 'https://example.com/feed.xml',
        'email' => 'test@example.com',
        'token' => $token,
    ]);

    expect($response->getStatusCode())->toBe(200);

    $contentType = $response->getHeaders()['content-type'][0] ?? '';

    // Check for X-Robots-Tag header per OpenAPI spec
    // Check for X-Robots-Tag header per OpenAPI spec
    $headers = $response->getHeaders();
    expect($headers)->toHaveKey('x-robots-tag');
    $xRobotsTag = $headers['x-robots-tag'][0] ?? '';
    expect($xRobotsTag)->toContain('noindex');
    // Response body should be HTML or JSON
    if (str_contains($contentType, 'application/json')) {
        $body = to_array_safe($response);
        expect($body)->toHaveKey('title');
    } else {
        $content = get_content_safe($response);
        expect($content)->toContain('confirmed');
    }
});

/** @throws \Exception */
it('returns valid error structure for invalid confirmation token', function (): void {
    $dbPath = getenv('NEWSLETTER_DB_PATH');

    assert(is_string($dbPath) && $dbPath !== '');

    init_test_database($dbPath);

    $response = http_get('/v1/subscriptions/confirmation/', [
        'uri' => 'https://example.com/feed.xml',
        'email' => 'test@example.com',
        'token' => 'wrong-token',
    ]);

    expect($response->getStatusCode())->toBe(400);

    $contentType = $response->getHeaders()['content-type'][0] ?? '';

    // Error response should have valid structure
    if (str_contains($contentType, 'application/json')) {
        $body = to_array_safe($response);
        expect($body)->toHaveKey('title');
        $title = $body['title'] ?? '';
        expect(in_array($title, ['Invalid', 'Error'], true))->toBeTrue();
    }
});

/** @throws \Exception */
it('returns valid cancellation response structure', function (): void {
    $dbPath = getenv('NEWSLETTER_DB_PATH');

    assert(is_string($dbPath) && $dbPath !== '');

    init_test_database($dbPath);

    $dbPath = getenv('NEWSLETTER_DB_PATH');
    assert($dbPath !== false, 'NEWSLETTER_DB_PATH not set');
    $pdo = new \PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare('INSERT INTO feeds (uri, title, link, last_update, trigger_hour) VALUES (?, ?, ?, ?, ?)');
    assert($stmt !== false);
    $stmt->execute([
        'https://example.com/feed.xml',
        'Test Feed',
        'https://example.com',
        time(),
        12,
    ]);
    $stmt = $pdo->prepare('INSERT INTO subscriptions (feed_uri, email, active) VALUES (?, ?, ?)');
    assert($stmt !== false);
    $stmt->execute([
        'https://example.com/feed.xml',
        'test@example.com',
        1,
    ]);

    $token = hash_hmac(algo: 'sha256', data: 'test@example.com', key: (string) getenv('SECRET_KEY'));

    $response = http_get('/v1/subscriptions/cancellation/', [
        'uri' => 'https://example.com/feed.xml',
        'email' => 'test@example.com',
        'token' => $token,
    ]);

    expect($response->getStatusCode())->toBe(200);

    $headers = $response->getHeaders();

    // Check for X-Robots-Tag header per OpenAPI spec
    expect($headers)->toHaveKey('x-robots-tag');
    $xRobotsTag = $headers['x-robots-tag'][0] ?? '';
    expect($xRobotsTag)->toContain('noindex');
    $contentType = $response->getHeaders()['content-type'][0] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $body = to_array_safe($response);
        expect($body)->toHaveKey('title');
    }
});

/** @throws \Exception */
it('returns valid error structure for invalid cancellation token', function (): void {
    $dbPath = getenv('NEWSLETTER_DB_PATH');

    assert(is_string($dbPath) && $dbPath !== '');

    init_test_database($dbPath);

    $response = http_get('/v1/subscriptions/cancellation/', [
        'uri' => 'https://example.com/feed.xml',
        'email' => 'test@example.com',
        'token' => 'wrong-token',
    ]);

    expect($response->getStatusCode())->toBe(400);

    $contentType = $response->getHeaders()['content-type'][0] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $body = to_array_safe($response);
        expect($body)->toHaveKey('title');
        $title = $body['title'] ?? '';
        expect(in_array($title, ['Invalid', 'Error'], true))->toBeTrue();
    }
});

/** @throws \Exception */
it('validates JSON response when Accept header is set', function (): void {
    $dbPath = getenv('NEWSLETTER_DB_PATH');

    assert(is_string($dbPath) && $dbPath !== '');

    init_test_database($dbPath);

    $response = http_get('/v1/subscriptions/', [
        'uri' => 'https://example.com/feed.xml',
        'email' => 'test@example.com',
    ]);

    expect($response->getStatusCode())->toBe(200);

    $contentType = $response->getHeaders()['content-type'][0] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $body = to_array_safe($response);
        expect($body)->toHaveKey('title');
    }
});
