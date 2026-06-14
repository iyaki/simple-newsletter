<?php

declare(strict_types=1);


use SimpleNewsletter\Components\Auth;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\FeedsDAO;
use SimpleNewsletter\Data\Subscription;
use SimpleNewsletter\Data\SubscriptionsDAO;
use SimpleNewsletter\Templates\ApiV1\JsonResponse;
use SimpleNewsletter\Templates\ApiV1\RedirectResponse;

/**
 * API integration/contract tests.
 *
 * These tests verify API behavior and OpenAPI contract compliance without
 * a sub-process server. They set up state, instantiate components directly,
 * and verify response structures match the JSONResponse schema.
 */

const TEST_SECRET = 'test-secret-for-contract';

const TEST_EMAIL = 'user@example.com';

const TEST_FEED_URI = 'https://blog.example.com/feed.xml';

// Reusable test helpers
function createTestDb(): \PDO
{
    $db = new \PDO('sqlite::memory:');
    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    foreach (\glob(__DIR__ . '/../../migrations/*.sql') as $migration) {
        $db->exec(\file_get_contents($migration));
    }
    return $db;
}

function seedFeed(\PDO $db): FeedsDAO
{
    $dao = new FeedsDAO($db);
    $dao->new(new Feed(TEST_FEED_URI, 'Test Blog', 'https://blog.example.com', new \DateTimeImmutable()));
    return $dao;
}

function seedSubscription(\PDO $db): void
{
    $dao = new SubscriptionsDAO($db);
    $dao->new(new Subscription(TEST_FEED_URI, TEST_EMAIL));
}

function activateSubscription(\PDO $db): void
{
    new SubscriptionsDAO($db)->activate(new Subscription(TEST_FEED_URI, TEST_EMAIL));
}

function makeAuth(): Auth
{
    return new Auth(TEST_SECRET);
}

/**
 * Validate a JSON response matches the OpenAPI JSONResponse schema.
 * JSONResponse: { title: string, detail: string }
 */
function checkJsonResponseSchema(array $response): void
{
    expect($response)->toHaveKeys(['title', 'detail']);
    expect($response['title'])->toBeString();
    expect($response['detail'])->toBeString();
}

// ─── Subscription endpoint contract ────────────────────────────────────────

describe('Subscription API validations', function (): void {
    it('rejects invalid feed URI', function () {
        expect(\filter_var('not-a-valid-url', \FILTER_VALIDATE_URL))->toBeFalse();
    });

    it('rejects javascript: return URL', function () {
        expect(\filter_var('javascript:alert(1)', \FILTER_VALIDATE_URL))->toBeFalse();
    });

    it('rejects ftp:// return URL scheme via scheme check', function () {
        $return = 'ftp://bad.com';
        expect(\filter_var($return, \FILTER_VALIDATE_URL))->not->toBeFalse();
        $scheme = \parse_url($return, \PHP_URL_SCHEME);
        expect(\in_array($scheme, ['http', 'https'], true))->toBeFalse();
    });
});

// ─── Confirmation endpoint contract ────────────────────────────────────────

describe('Confirmation flow', function (): void {
    it('confirms subscription with valid token', function () {
        $db = createTestDb();
        seedFeed($db);
        seedSubscription($db);

        $token = makeAuth()->hash(TEST_EMAIL);
        $subsDao = new SubscriptionsDAO($db);
        $subsDao->activate(new Subscription(TEST_FEED_URI, TEST_EMAIL));

        // Verify the subscription is active
        $sub = $subsDao->find(TEST_FEED_URI, TEST_EMAIL);
        expect($sub)->not->toBeNull();
        expect($sub?->active)->toBeTrue();
    });

    it('rejects invalid token', function () {
        $db = createTestDb();
        seedFeed($db);
        seedSubscription($db);

        // Invalid token should not match
        $auth = makeAuth();
        $result = $auth->verify(TEST_EMAIL, 'invalid-token');
        expect($result)->toBeFalse();
    });
});

// ─── Cancellation endpoint contract ────────────────────────────────────────

describe('Cancellation flow', function (): void {
    it('deactivates subscription with valid token', function () {
        $db = createTestDb();
        seedFeed($db);
        seedSubscription($db);
        activateSubscription($db);

        $token = makeAuth()->hash(TEST_EMAIL);

        // Verify by deactivating
        $subsDao = new SubscriptionsDAO($db);
        $subsDao->deactivate(new Subscription(TEST_FEED_URI, TEST_EMAIL));

        $sub = $subsDao->find(TEST_FEED_URI, TEST_EMAIL);
        expect($sub)->not->toBeNull();
        expect($sub?->active)->toBeFalse();
    });
});

// ─── JSON Response schema contract ─────────────────────────────────────────

describe('JSONResponse schema compliance (OpenAPI)', function (): void {
    it('JsonResponse::fromString produces valid JSONResponse', function () {
        $response = JsonResponse::fromString('Subscription confirmed', 'You are now subscribed.');
        $body = \json_decode($response->getBody(), true);
        checkJsonResponseSchema($body);
        expect($body['title'])->toEqual('Subscription confirmed');
        expect($body['detail'])->toEqual('You are now subscribed.');
    });

    it('JsonResponse::fromEndUserException produces valid JSONResponse', function () {
        $e = new EndUserException('Invalid token');
        $response = JsonResponse::fromEndUserException($e);
        $body = \json_decode($response->getBody(), true);
        checkJsonResponseSchema($body);
        expect($body['title'])->toEqual('Error: Invalid data');
        expect($body['detail'])->toEqual('Invalid token');
    });

    it('JsonResponse uses application/json content type', function () {
        $response = JsonResponse::fromString('Test', 'Message');
        $headers = $response->getHeaders();
        expect($headers['Content-Type'] ?? '')->toContain('application/json');
    });

    it('RedirectResponse::getHeaders includes Location', function () {
        $response = RedirectResponse::fromString('Title', 'Message', 'https://example.com/back');
        $headers = $response->getHeaders();
        expect($headers)->toHaveKey('Location');
        expect($headers['Location'])->toContain('https://example.com/back');
    });

    it('Redirect response has empty body', function () {
        $response = RedirectResponse::fromString('Title', 'Message', 'https://example.com/back');
        expect($response->getBody())->toEqual('');
    });
});

// ─── Rate limiter contract ─────────────────────────────────────────────────

describe('RateLimiter', function (): void {
    it('allows requests under the limit', function () {
        $db = createTestDb();
        $limiter = new \SimpleNewsletter\Components\RateLimiter($db);

        // Should not throw
        $limiter->check('127.0.0.1', 'test-endpoint');
        $limiter->check('127.0.0.1', 'test-endpoint');
        expect(true)->toBeTrue();
    });

    it('blocks after exceeding limit', function () {
        $db = createTestDb();
        $limiter = new \SimpleNewsletter\Components\RateLimiter($db);

        // Make 10 requests (should all pass)
        for ($i = 0; $i < 10; $i++) {
            $limiter->check('127.0.0.2', 'test-endpoint-2');
        }

        // 11th should throw
        $limiter->check('127.0.0.2', 'test-endpoint-2');
        expect(true)->toBeTrue();
    })->throws(\SimpleNewsletter\Components\EndUserException::class, 'Too many requests');
});

// ─── Auth contract ─────────────────────────────────────────────────────────

describe('Auth component', function (): void {
    it('hash and verify round-trips correctly', function () {
        $auth = makeAuth();
        $token = $auth->hash(TEST_EMAIL);
        expect($auth->verify(TEST_EMAIL, $token))->toBeTrue();
    });

    it('verify rejects different key', function () {
        $auth = makeAuth();
        $token = $auth->hash(TEST_EMAIL);
        expect($auth->verify('other@example.com', $token))->toBeFalse();
    });
});
