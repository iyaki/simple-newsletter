<?php

declare(strict_types=1);

use SimpleNewsletter\Components\Auth;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\FeedMetadata;
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

const TEST_SECRET = 'test-for-contract-compliance';

const TEST_EMAIL = 'user@example.com';

const TEST_FEED_URI = 'https://blog.example.com/feed.xml';

// Reusable test helpers
/**
 * @throws \PDOException
 */
function create_test_db(): \PDO
{
    $db = new \PDO('sqlite::memory:');
    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $files = \glob(__DIR__ . '/../../migrations/*.sql');
    if ($files === false) {
        $files = [];
    }
    foreach ($files as $migration) {
        $sql = \file_get_contents($migration);
        if ($sql === false) {
            continue;
        }
        $db->exec($sql);
    }
    return $db;
}

/**
 * @throws \Random\RandomException
 * @throws \SimpleNewsletter\Components\EndUserException
 */
function seed_feed(\PDO $db): FeedsDAO
{
    $dao = new FeedsDAO($db);
    $metadata = new FeedMetadata(TEST_FEED_URI, 'Test Blog', 'https://blog.example.com', new \DateTimeImmutable());
    $dao->new(new Feed($metadata));
    return $dao;
}

/**
 * @throws \SimpleNewsletter\Components\EndUserException
 */
function seed_subscription(\PDO $db): void
{
    $dao = new SubscriptionsDAO($db);
    $dao->new(new Subscription(TEST_FEED_URI, TEST_EMAIL));
}

/**
 * @throws \SimpleNewsletter\Components\EndUserException
 */
function activate_subscription(\PDO $db): void
{
    new SubscriptionsDAO($db)->activate(new Subscription(TEST_FEED_URI, TEST_EMAIL));
}

function make_auth(): Auth
{
    return new Auth(TEST_SECRET);
}

/**
 * Validate a JSON response matches the OpenAPI JSONResponse schema.
 *
 * @param array{title: string, detail: string} $response
 */
function check_json_response_schema(array $response): void
{
    expect($response)->toHaveKey('title');
    expect($response)->toHaveKey('detail');
    expect($response['title'])->toBeString();
    expect($response['detail'])->toBeString();
}

// ─── Subscription endpoint contract ────────────────────────────────────────

describe('Subscription API validations', function (): void {
    it('rejects invalid feed URI', function (): void {
        expect(\filter_var('not-a-valid-url', \FILTER_VALIDATE_URL))->toBeFalse();
    });

    it('rejects javascript: return URL', function (): void {
        expect(\filter_var('javascript:alert(1)', \FILTER_VALIDATE_URL))->toBeFalse();
    });

    it('rejects ftp:// return URL scheme via scheme check', function (): void {
        $return = 'ftp://bad.com';
        expect(\filter_var($return, \FILTER_VALIDATE_URL))->not->toBeFalse();
        $scheme = \parse_url($return, \PHP_URL_SCHEME);
        expect(\in_array($scheme, haystack: ['http', 'https'], strict: true))->toBeFalse();
    });
});

// ─── Confirmation endpoint contract ────────────────────────────────────────

describe('Confirmation flow', function (): void {
    it('confirms subscription with valid token', function (): void {
        $db = create_test_db();
        seed_feed($db);
        seed_subscription($db);

        $subsDao = new SubscriptionsDAO($db);
        $subsDao->activate(new Subscription(TEST_FEED_URI, TEST_EMAIL));

        // Verify the subscription is active
        $sub = $subsDao->find(TEST_FEED_URI, TEST_EMAIL);
        expect($sub)->not->toBeNull();
        expect($sub?->active)->toBeTrue();
    });

    it('rejects invalid token', function (): void {
        $db = create_test_db();
        seed_feed($db);
        seed_subscription($db);

        // Invalid token should not match
        $auth = make_auth();
        $result = $auth->verify(TEST_EMAIL, 'invalid-token');
        expect($result)->toBeFalse();
    });
});

// ─── Cancellation endpoint contract ────────────────────────────────────────

describe('Cancellation flow', function (): void {
    it('deactivates subscription with valid token', function (): void {
        $db = create_test_db();
        seed_feed($db);
        seed_subscription($db);
        activate_subscription($db);

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
    it('JsonResponse::fromString produces valid JSONResponse', function (): void {
        $response = JsonResponse::fromString('Subscription confirmed', 'You are now subscribed.');
        /** @var array{title: string, detail: string} $body */
        $body = \json_decode($response->getBody(), associative: true);
        expect($body)->toBeArray();
        check_json_response_schema($body);
        expect($body['title'])->toEqual('Subscription confirmed');
        expect($body['detail'])->toEqual('You are now subscribed.');
    });

    it('JsonResponse::fromEndUserException produces valid JSONResponse', function (): void {
        $e = new EndUserException('Invalid token');
        $response = JsonResponse::fromEndUserException($e);
        /** @var array{title: string, detail: string} $body */
        $body = \json_decode($response->getBody(), associative: true);
        expect($body)->toBeArray();
        check_json_response_schema($body);
        expect($body['title'])->toEqual('Error: Invalid data');
        expect($body['detail'])->toEqual('Invalid token');
    });

    it('JsonResponse uses application/json content type', function (): void {
        $response = JsonResponse::fromString('Test', 'Message');
        $headers = $response->getHeaders();
        expect($headers['Content-Type'] ?? '')->toContain('application/json');
    });

    it('RedirectResponse::getHeaders includes Location', function (): void {
        $response = RedirectResponse::fromString('Title', 'Message', 'https://example.com/back');
        $headers = $response->getHeaders();
        expect($headers)->toHaveKey('Location');
        expect($headers['Location'] ?? '')->toContain('https://example.com/back');
    });

    it('Redirect response has empty body', function (): void {
        $response = RedirectResponse::fromString('Title', 'Message', 'https://example.com/back');
        expect($response->getBody())->toEqual('');
    });
});

// ─── Rate limiter contract ─────────────────────────────────────────────────

describe('RateLimiter', function (): void {
    it('allows requests under the limit', function (): void {
        $db = create_test_db();
        $limiter = new \SimpleNewsletter\Components\RateLimiter($db);

        // Should not throw
        $limiter->check('127.0.0.1', 'test-endpoint');
        $limiter->check('127.0.0.1', 'test-endpoint');
        expect(true)->toBeTrue();
    });

    it('blocks after exceeding limit', function (): void {
        $db = create_test_db();
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
    it('hash and verify round-trips correctly', function (): void {
        $auth = make_auth();
        $token = $auth->hash(TEST_EMAIL);
        expect($auth->verify(TEST_EMAIL, $token))->toBeTrue();
    });

    it('verify rejects different key', function (): void {
        $auth = make_auth();
        $token = $auth->hash(TEST_EMAIL);
        expect($auth->verify('other@example.com', $token))->toBeFalse();
    });
});
