<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface;

/** @param array<string, mixed> $queryParams */
function e2e_sub_get(string $path, array $queryParams = []): ResponseInterface
{
    static $client = null;
    if ($client === null) {
        $client = HttpClient::create(['base_uri' => 'http://localhost:8080']);
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
        'uri' => 'https://example.com/feed.xml',
        'email' => 'test@example.com',
    ]);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getHeaders()['content-type'][0] ?? '')->toContain('text/html');

    $content = $response->getContent(false);
    expect($content)->toContain('email confirmation');

    // 2. Verify subscription created in DB (unconfirmed)
    $dbPath = \getenv('NEWSLETTER_DB_PATH');
    assert(\is_string($dbPath) && $dbPath !== '');
    $pdo = new \PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare('SELECT * FROM subscriptions WHERE feed_uri = ? AND email = ?');
    assert($stmt instanceof \PDOStatement);
    $stmt->execute(['https://example.com/feed.xml', 'test@example.com']);
    /** @var array{active: int, ...}|false $sub */
    $sub = $stmt->fetch(\PDO::FETCH_ASSOC);
    assert(\is_array($sub));
    expect($sub['active'])->toBe(0); // Unconfirmed

    // 3. Generate confirmation token
    $rawKey = \getenv('SECRET_KEY');
    $key = \is_string($rawKey) ? $rawKey : '';
    $token = \hash_hmac('sha256', 'test@example.com', $key);

    // 4. Confirm subscription
    $confirmResponse = e2e_sub_get('/v1/subscriptions/confirmation/', [
        'feed_uri' => 'https://example.com/feed.xml',
        'email' => 'test@example.com',
        'token' => $token,
    ]);

    expect($confirmResponse->getStatusCode())->toBe(200);

    // 5. Verify subscription active in DB
    /** @var array{active: int, ...}|false $confirmedSub */
    $confirmedSub = $stmt->fetch(\PDO::FETCH_ASSOC);
    assert(\is_array($confirmedSub));
    expect($confirmedSub['active'])->toBe(1);
});

/** @throws \Exception */
it('rejects invalid feed URI', function (): void {
    $response = e2e_sub_get('/v1/subscriptions/', [
        'uri' => 'not-a-valid-url',
        'email' => 'test@example.com',
    ]);

    expect($response->getStatusCode())->toBe(400);
    $content = $response->getContent(false);
    expect($content)->toContain('Invalid');
});

/** @throws \Exception */
it('rejects missing required fields', function (): void {
    $response = e2e_sub_get('/v1/subscriptions/', [
        'uri' => 'https://example.com/feed.xml',
        // email missing
    ]);

    expect($response->getStatusCode())->toBe(400);
});
