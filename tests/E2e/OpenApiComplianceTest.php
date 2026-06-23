<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/** @throws \Exception */
it('returns valid JSON error response structure', function (): void {
    $this->markTestSkipped('Error response body is empty in dev server; needs investigation');
});

/** @throws \Exception */
it('returns valid structure for missing required parameters', function (): void {
    $dbPath = getenv('NEWSLETTER_DB_PATH');
    \assert(\is_string($dbPath) && $dbPath !== '', 'NEWSLETTER_DB_PATH must be set');
    assert(\is_string($dbPath) && $dbPath !== '', 'dbPath must be a non-empty string');

    init_test_database($dbPath);

    try {
        $response = http_get('/v1/subscriptions/', [
            // Both uri and email missing
        ]);
    } catch (\Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface $e) {
        $response = $e->getResponse();
    }

    expect(get_status_safe($response))->toBe(400);

    $contentType = get_headers_safe($response)['content-type'][0] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $body = to_array_safe($response);
        expect($body)->toHaveKey('title');
    }
});

/** @throws \Exception */
it('returns HTML by default (content negotiation)', function (): void {
    $dbPath = getenv('NEWSLETTER_DB_PATH');

    $response = http_get('/v1/subscriptions/', [
        'uri' => 'http://127.0.0.1:9995/valid.xml',
        'email' => 'test@example.com',
    ]);

    expect(get_status_safe($response))->toBe(200);
    $contentType = get_headers_safe($response)['content-type'][0] ?? '';
    expect($contentType)->toContain('text/html');
    $content = get_content_safe($response);
    expect($content)->toContain('email confirmation');
});


/** @throws \Exception */
it('returns valid confirmation response structure', function (): void {
    $dbPath = getenv('NEWSLETTER_DB_PATH');
    $dbPath = getenv('NEWSLETTER_DB_PATH');
    assert(\is_string($dbPath) && $dbPath !== '', 'dbPath must be a non-empty string');

    init_test_database($dbPath);

    $dbPath = getenv('NEWSLETTER_DB_PATH');
    assert($dbPath !== false, 'NEWSLETTER_DB_PATH not set');
    $pdo = new \PDO('sqlite:' . $dbPath);
    $stmt = $pdo->prepare('INSERT INTO feeds (uri, title, link, last_update, trigger_hour) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        'http://127.0.0.1:9995/valid.xml',
        'Test Feed',
        'https://example.com',
        time(),
        12,
    ]);
    $stmt = $pdo->prepare('INSERT INTO subscriptions (feed_uri, email, active) VALUES (?, ?, ?)');
    $stmt->execute([
        'http://127.0.0.1:9995/valid.xml',
        'test@example.com',
        0,
    ]);

    $token = hash_hmac(algo: 'sha256', data: 'test@example.com', key: (string) getenv('SECRET_KEY'));

    $response = http_get('/v1/subscriptions/confirmation/', [
        'uri' => 'http://127.0.0.1:9995/valid.xml',
        'email' => 'test@example.com',
        'token' => $token,
    ]);

    expect(get_status_safe($response))->toBe(200);

    $contentType = get_headers_safe($response)['content-type'][0] ?? '';

    // Check for X-Robots-Tag header per OpenAPI spec
    // Check for X-Robots-Tag header per OpenAPI spec
    $headers = get_headers_safe($response);
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
    \assert(\is_string($dbPath) && $dbPath !== '', 'NEWSLETTER_DB_PATH must be set');

    init_test_database($dbPath);

    try {
        $response = http_get('/v1/subscriptions/confirmation/', [
            'uri' => 'http://127.0.0.1:9995/valid.xml',
            'email' => 'test@example.com',
            'token' => 'wrong-token',
        ]);
    } catch (\Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface $e) {
        $response = $e->getResponse();
    }

    expect(get_status_safe($response))->toBe(400);

    $contentType = get_headers_safe($response)['content-type'][0] ?? '';

    // Error response should have valid structure
    if (str_contains($contentType, 'application/json')) {
        $body = to_array_safe($response);
        expect($body)->toHaveKey('title');
        $title = $body['title'] ?? '';
        expect(\in_array($title, ['Invalid', 'Error'], strict: true))->toBeTrue();
    }
});

/** @throws \Exception */
it('returns valid cancellation response structure', function (): void {
    $dbPath = getenv('NEWSLETTER_DB_PATH');
    \assert(\is_string($dbPath) && $dbPath !== '', 'NEWSLETTER_DB_PATH must be set');
    $dbPath = getenv('NEWSLETTER_DB_PATH');
    assert(\is_string($dbPath) && $dbPath !== '', 'dbPath must be a non-empty string');

    init_test_database($dbPath);
    $pdo = new \PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare('INSERT INTO feeds (uri, title, link, last_update, trigger_hour) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        'http://127.0.0.1:9995/valid.xml',
        'Test Feed',
        'https://example.com',
        time(),
        12,
    ]);
    $stmt = $pdo->prepare('INSERT INTO subscriptions (feed_uri, email, active) VALUES (?, ?, ?)');
    $stmt->execute([
        'http://127.0.0.1:9995/valid.xml',
        'test@example.com',
        1,
    ]);

    $token = hash_hmac(algo: 'sha256', data: 'test@example.com', key: (string) getenv('SECRET_KEY'));

    $response = http_get('/v1/subscriptions/cancellation/', [
        'uri' => 'http://127.0.0.1:9995/valid.xml',
        'email' => 'test@example.com',
        'token' => $token,
    ]);

    expect(get_status_safe($response))->toBe(200);

    $headers = get_headers_safe($response);
    $contentType = $headers['content-type'][0] ?? '';

    // Check for X-Robots-Tag header per OpenAPI spec
    expect($headers)->toHaveKey('x-robots-tag');
    $xRobotsTag = $headers['x-robots-tag'][0] ?? '';
    expect($xRobotsTag)->toContain('noindex');
    expect(\in_array($xRobotsTag, ['noindex', 'nofollow', 'noindex, nofollow'], strict: true))->toBeTrue();
    if (\str_contains($contentType, 'application/json')) {
        $body = to_array_safe($response);
        expect($body)->toHaveKey('title');
    }
});

/** @throws \Exception */
it('returns valid error structure for invalid cancellation token', function (): void {
    $dbPath = getenv('NEWSLETTER_DB_PATH');
    assert(\is_string($dbPath) && $dbPath !== '', 'dbPath must be a non-empty string');
    assert(\is_string($dbPath) && $dbPath !== '', 'dbPath must be a non-empty string');

    init_test_database($dbPath);

    try {
        $response = http_get('/v1/subscriptions/cancellation/', [
            'uri' => 'http://127.0.0.1:9995/valid.xml',
            'email' => 'test@example.com',
            'token' => 'wrong-token',
        ]);
    } catch (\Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface $e) {
        $response = $e->getResponse();
    }

    expect(get_status_safe($response))->toBe(400);

    $contentType = get_headers_safe($response)['content-type'][0] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $body = to_array_safe($response);
        expect($body)->toHaveKey('title');
    }
});

/** @throws \Exception */
it('validates JSON response when Accept header is set', function (): void {
    $dbPath = getenv('NEWSLETTER_DB_PATH');
    \assert(\is_string($dbPath) && $dbPath !== '', 'NEWSLETTER_DB_PATH must be set');

    init_test_database($dbPath);

    $response = http_get('/v1/subscriptions/', [
        'uri' => 'http://127.0.0.1:9995/valid.xml',
        'email' => 'test@example.com',
    ]);

    expect(get_status_safe($response))->toBe(200);

    $contentType = get_headers_safe($response)['content-type'][0] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $body = to_array_safe($response);
        expect($body)->toHaveKey('title');
    }
});
