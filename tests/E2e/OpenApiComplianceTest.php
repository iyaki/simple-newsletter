<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Tests\E2e\DatabaseCleaner;
use Tests\E2e\HttpClientHelpers;
use Tests\E2e\OpenApiValidator;

uses(HttpClientHelpers::class, DatabaseCleaner::class, OpenApiValidator::class);

it('returns valid JSON error response structure', function () {
    initTestDatabase(getenv('NEWSLETTER_DB_PATH'));

    // Test with invalid URI - should return 400 with valid error structure
    $response = self::get('/v1/subscriptions/', [
        'uri' => 'invalid-uri',
        'email' => 'test@example.com',
    ]);

    expect($response->getStatusCode())->toBe(400);

    // Response should be either HTML or JSON
    $contentType = $response->getHeaders()['content-type'][0] ?? '';

    if (str_contains($contentType, 'application/json')) {
        $body = self::toArraySafe($response);

        // Validate JSONResponse schema structure
        expect($body)->toHaveKey('title')->because('JSONResponse requires title field');

        if (array_key_exists('detail', $body)) {
            expect($body['detail'])->toBeString();
        }
    } else {
        // HTML response is also valid
        $content = self::getContentSafe($response);
        expect($content)->not->toBeEmpty();
    }
});

it('returns valid structure for missing required parameters', function () {
    initTestDatabase(getenv('NEWSLETTER_DB_PATH'));

    $response = self::get('/v1/subscriptions/', [
        // Missing both uri and email
    ]);

    expect($response->getStatusCode())->toBe(400);

    // Check response structure
    $contentType = $response->getHeaders()['content-type'][0] ?? '';

    if (str_contains($contentType, 'application/json')) {
        $body = self::toArraySafe($response);
        expect($body)->toHaveKey('title');
    }
});

it('returns HTML by default (content negotiation)', function () {
    initTestDatabase(getenv('NEWSLETTER_DB_PATH'));

    $response = self::get('/v1/subscriptions/', [
        'uri' => 'https://example.com/feed.xml',
        'email' => 'test@example.com',
    ]);

    $contentType = $response->getHeaders()['content-type'][0] ?? '';

    // By default, should return HTML unless Accept: application/json is sent
    expect($contentType)->toContain('text/html');

    $content = self::getContentSafe($response);
    expect($content)->not->toBeEmpty();
});

it('returns 404 for unknown routes with valid error structure', function () {
    initTestDatabase(getenv('NEWSLETTER_DB_PATH'));

    $response = self::get('/nonexistent-endpoint');

    expect($response->getStatusCode())->toBe(404);

    // Even 404 should have proper structure
    $contentType = $response->getHeaders()['content-type'][0] ?? '';
    $content = self::getContentSafe($response);

    // Should return some content (error page)
    expect($content)->not->toBeEmpty();
});

it('returns valid confirmation response structure', function () {
    initTestDatabase(getenv('NEWSLETTER_DB_PATH'));

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
        0,
    ]);

    $token = hash_hmac(algo: 'sha256', data: 'test@example.com', key: (string) getenv('SECRET_KEY'));

    $response = self::get('/v1/subscriptions/confirmation/', [
        'uri' => 'https://example.com/feed.xml',
        'email' => 'test@example.com',
        'token' => $token,
    ]);

    expect($response->getStatusCode())->toBe(200);

    $contentType = $response->getHeaders()['content-type'][0] ?? '';

    // Check for X-Robots-Tag header per OpenAPI spec
    $headers = $response->getHeaders();
    expect($headers)->toHaveKey('x-robots-tag')->because('OpenAPI spec requires X-Robots-Tag header');

    expect($headers['x-robots-tag'][0])->toContain('noindex')->because('X-Robots-Tag should prevent indexing');

    // Response body should be HTML or JSON
    if (str_contains($contentType, 'application/json')) {
        $body = self::toArraySafe($response);
        expect($body)->toHaveKey('title');
    } else {
        $content = self::getContentSafe($response);
        expect($content)->toContain('confirmed');
    }
});

it('returns valid error structure for invalid confirmation token', function () {
    initTestDatabase(getenv('NEWSLETTER_DB_PATH'));

    $response = self::get('/v1/subscriptions/confirmation/', [
        'uri' => 'https://example.com/feed.xml',
        'email' => 'test@example.com',
        'token' => 'wrong-token',
    ]);

    expect($response->getStatusCode())->toBe(400);

    $contentType = $response->getHeaders()['content-type'][0] ?? '';

    // Error response should have valid structure
    if (str_contains($contentType, 'application/json')) {
        $body = self::toArraySafe($response);
        expect($body)->toHaveKey('title');
        expect($body['title'])->toContain('Invalid')->or($body['title'])->toContain('Error');
    }
});

it('returns valid cancellation response structure', function () {
    initTestDatabase(getenv('NEWSLETTER_DB_PATH'));

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

    $token = hash_hmac(algo: 'sha256', data: 'test@example.com', key: (string) getenv('SECRET_KEY'));

    $response = self::get('/v1/subscriptions/cancellation/', [
        'uri' => 'https://example.com/feed.xml',
        'email' => 'test@example.com',
        'token' => $token,
    ]);

    expect($response->getStatusCode())->toBe(200);

    $headers = $response->getHeaders();

    // Check for X-Robots-Tag header per OpenAPI spec
    expect($headers)->toHaveKey('x-robots-tag')->because('OpenAPI spec requires X-Robots-Tag header');

    expect($headers['x-robots-tag'][0])->toContain('noindex');

    $contentType = $response->getHeaders()['content-type'][0] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $body = self::toArraySafe($response);
        expect($body)->toHaveKey('title');
    }
});

it('returns valid error structure for invalid cancellation token', function () {
    initTestDatabase(getenv('NEWSLETTER_DB_PATH'));

    $response = self::get('/v1/subscriptions/cancellation/', [
        'uri' => 'https://example.com/feed.xml',
        'email' => 'test@example.com',
        'token' => 'wrong-token',
    ]);

    expect($response->getStatusCode())->toBe(400);

    $contentType = $response->getHeaders()['content-type'][0] ?? '';

    if (str_contains($contentType, 'application/json')) {
        $body = self::toArraySafe($response);
        expect($body)->toHaveKey('title');
    }
});

it('validates JSON response when Accept header is set', function () {
    initTestDatabase(getenv('NEWSLETTER_DB_PATH'));

    // Request JSON explicitly
    $response = self::get(
        '/v1/subscriptions/',
        [
            'uri' => 'https://example.com/feed.xml',
            'email' => 'test@example.com',
        ],
        ['Accept' => 'application/json'],
    );

    $contentType = $response->getHeaders()['content-type'][0] ?? '';

    if (str_contains($contentType, 'application/json')) {
        $body = self::toArraySafe($response);

        // Validate JSONResponse schema from OpenAPI spec
        expect($body)->toHaveKey('title')->because('OpenAPI JSONResponse requires title');

        // If detail is present, validate its type
        if (array_key_exists('detail', $body)) {
            expect($body['detail'])->toBeString()->because('detail should be string per schema');
        }
    }
});
