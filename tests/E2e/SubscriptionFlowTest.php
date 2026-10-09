<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface;

/** @param array<string, mixed> $queryParams */
function e2e_sub_get(string $path, array $queryParams = []): ResponseInterface
{
    static $client = null;
    static $baseUrl = null;

    if ($baseUrl === null) {
        $envUrl = \getenv('E2E_BASE_URL');
        $baseUrl = $envUrl !== false ? $envUrl : 'http://localhost:8080';
    }

    if ($client === null) {
        $client = HttpClient::create(['base_uri' => $baseUrl]);
    }
    $url = $path;
    if (\count($queryParams) > 0) {
        $url .= '?' . \http_build_query($queryParams);
    }

    return $client->request('GET', $url, [
        'headers' => [],
    ]);
}

beforeEach(function (): void {
    init_test_database((string) \getenv('NEWSLETTER_DB_PATH'));
});

/** @throws \Exception */
it('completes subscription flow end-to-end', function (): void {
    // 1. Initial subscription request
    $response = e2e_sub_get('/v1/subscriptions/', [
        'uri' => 'http://' . e2e_feed_host() . ':9995/valid.xml',
        'email' => 'test@example.com',
        'return' => 'https://example.com',
        'redirect' => 'false',
    ]);

    expect(get_status_safe($response))->toBe(200);
    expect(get_headers_safe($response)['content-type'][0] ?? '')->toContain('text/html');

    $content = get_content_safe($response);
    expect($content)->toContain('email confirmation');

    // 2. Verify subscription created in DB (unconfirmed)
    $dbPath = \getenv('NEWSLETTER_DB_PATH');
    $pdo = new \PDO('sqlite:' . $dbPath);
    $stmt = $pdo->prepare('SELECT * FROM subscriptions WHERE feed_uri = ? AND email = ?');
    \assert($stmt instanceof \PDOStatement, 'stmt should be prepared');
    $stmt->execute(['http://' . e2e_feed_host() . ':9995/valid.xml', 'test@example.com']);
    /** @var array{active: int, ...}|false $sub */
    $sub = $stmt->fetch(\PDO::FETCH_ASSOC);
    \assert(\is_array($sub), 'subscription should exist');
    expect($sub['active'])->toBe(0);
    $rawKey = \getenv('SECRET_KEY');
    \assert(\is_string($rawKey), 'SECRET_KEY must be set');
    // Token binding (audit fix): MAC = HMAC('confirm|feedUri|email|nonce', SECRET_KEY).
    // The row was created by add() above — read its nonce from the DB.
    $nonceStmt = $pdo->prepare('SELECT token_nonce FROM subscriptions WHERE feed_uri = ? AND email = ?');
    \assert($nonceStmt instanceof \PDOStatement, 'stmt should be prepared');
    $nonceStmt->execute(['http://' . e2e_feed_host() . ':9995/valid.xml', 'test@example.com']);
    $nonce = (string) ($nonceStmt->fetchColumn() ?: '');
    $token = hash_hmac('sha256', 'confirm|http://' . e2e_feed_host() . ':9995/valid.xml|test@example.com|' . $nonce, $rawKey);
    // 4. Confirm subscription
    $confirmResponse = e2e_sub_get('/v1/subscriptions/confirmation/', [
        'uri' => 'http://' . e2e_feed_host() . ':9995/valid.xml',
        'email' => 'test@example.com',
        'token' => $token,
    ]);

    expect(get_status_safe($confirmResponse))->toBe(200);

    // 5. Verify subscription active in DB via a FRESH connection: the
    // connection above started its read before the confirm request, and this
    // PDO sqlite build keeps that snapshot alive on the connection even after
    // closeCursor(), so re-executing there observes the pre-confirm state.
    $postConfirm = new \PDO('sqlite:' . $dbPath);
    $postConfirm->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $stmt2 = $postConfirm->prepare('SELECT * FROM subscriptions WHERE feed_uri = ? AND email = ?');
    \assert($stmt2 instanceof \PDOStatement, 'stmt should be prepared');
    $stmt2->execute(['http://' . e2e_feed_host() . ':9995/valid.xml', 'test@example.com']);
    /** @var array{active: int, ...}|false $confirmedSub */
    $confirmedSub = $stmt2->fetch(\PDO::FETCH_ASSOC);
    \assert(\is_array($confirmedSub), 'confirmed subscription should exist');
    expect($confirmedSub['active'])->toBe(1);
});
/** @throws \Exception */
it('rejects invalid feed URI', function (): void {
    $response = e2e_sub_get('/v1/subscriptions/', [
        'uri' => 'not-a-valid-url',
        'email' => 'test@example.com',
    ]);

    expect(get_status_safe($response))->toBe(400);
    $content = $response->getContent(false);
    expect($content)->toContain('Invalid');
});

/** @throws \Exception */
it('rejects missing required fields', function (): void {
    $response = e2e_sub_get('/v1/subscriptions/', [
        'uri' => 'http://' . e2e_feed_host() . ':9995/valid.xml',
        // email missing
    ]);

    expect(get_status_safe($response))->toBe(400);
});
