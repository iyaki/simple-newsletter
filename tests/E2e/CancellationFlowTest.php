<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @param array<string, string> $queryParams
 * @throws TransportExceptionInterface
 */
function e2e_get_cancel(string $path, array $queryParams = []): ResponseInterface
{
    static $client = null;
    if ($client === null) {
        $client = \Symfony\Component\HttpClient\HttpClient::create(['base_uri' => 'http://localhost:8080']);
    }
    $url = $path;
    if (\count($queryParams) > 0) {
        $url .= '?' . \http_build_query($queryParams);
    }

    return $client->request('GET', $url, [
        'headers' => [],
    ]);
}

/**
 * @throws \PDOException
 */
function e2e_clean_test_database(): void
{
    $dbPath = \getenv('NEWSLETTER_DB_PATH');
    if ($dbPath && \file_exists($dbPath)) {
        $pdo = new \PDO("sqlite:{$dbPath}");
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('DELETE FROM subscriptions');
        $pdo->exec('DELETE FROM feeds');
    }
}

beforeEach(
    /** @throws \PDOException */
    function (): void {
        e2e_clean_test_database();
        init_test_database((string) \getenv('NEWSLETTER_DB_PATH'));

        // Create active subscription with feed
        $pdo = new \PDO('sqlite:' . (string) \getenv('NEWSLETTER_DB_PATH'));
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        /** @var \PDOStatement $stmt */
        $stmt = $pdo->prepare('INSERT INTO feeds (uri, title, link, last_update, trigger_hour) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            'http://127.0.0.1:9995/valid.xml',
            'Test Feed',
            'https://example.com',
            time(),
            12,
        ]);
        /** @var \PDOStatement $stmt */
        $stmt = $pdo->prepare('INSERT INTO subscriptions (feed_uri, email, active) VALUES (?, ?, ?)');
        $stmt->execute([
            'http://127.0.0.1:9995/valid.xml',
            'test@example.com',
            1,
        ]);
    },
);

it(
    'cancels subscription with valid token',
    /**
     * @throws TransportExceptionInterface
     * @throws \PDOException
     */
    function (): void {
        $token = hash_hmac(algo: 'sha256', data: 'test@example.com', key: (string) getenv('SECRET_KEY'));

        $response = e2e_get_cancel('/v1/subscriptions/cancellation/', [
            'feed_uri' => 'http://127.0.0.1:9995/valid.xml',
            'email' => 'test@example.com',
            'token' => $token,
        ]);

        expect($response->getStatusCode())->toBe(200);

        // Verify subscription is inactive
        $pdo = new \PDO('sqlite:' . (string) \getenv('NEWSLETTER_DB_PATH'));
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        /** @var \PDOStatement $stmt */
        $stmt = $pdo->prepare('SELECT active FROM subscriptions WHERE feed_uri = ? AND email = ?');
        $stmt->execute(['http://127.0.0.1:9995/valid.xml', 'test@example.com']);
        /** @var array<string, mixed> $sub */
        $sub = $stmt->fetch(\PDO::FETCH_ASSOC);
        expect($sub['active'] ?? 0)->toBe(0);
    },
);

it(
    'rejects invalid cancellation token',
    /** @throws TransportExceptionInterface */
    function (): void {
        $response = e2e_get_cancel('/v1/subscriptions/cancellation/', [
            'feed_uri' => 'http://127.0.0.1:9995/valid.xml',
            'email' => 'test@example.com',
            'token' => 'invalid-token',
        ]);

        expect($response->getStatusCode())->toBe(400);
    },
);
