<?php

declare(strict_types=1);

// Set test environment variables
$testDbPath = __DIR__ . '/../../data/test-e2e.db';
putenv('NEWSLETTER_DB_PATH=' . $testDbPath);
putenv('SECRET_KEY=test-e2e-secret-key-32chars!');
putenv('SERVER_NAME=http://localhost:8080');
putenv('URI_SELF=http://localhost:8080');
// The e2e feed server is a loopback fixture: lift the feed egress policy.
putenv('NEWSLETTER_ALLOW_PRIVATE_FEEDS=1');
// Disable Sentry for e2e tests
putenv('SENTRY_DSN=');

require __DIR__ . '/../../vendor/autoload.php';

/**
 * Hostname the app should use to reach the test feed server.
 *
 * Default `127.0.0.1` matches the legacy `php -S 127.0.0.1:9995` runner. When
 * the app runs inside a container (prod e2e runner), the runner exports
 * `E2E_FEED_HOST=host.docker.internal` so the app reaches the feed server on
 * the host.
 *
 * @return non-empty-string
 */
function e2e_feed_host(): string
{
    $h = \getenv('E2E_FEED_HOST');
    return \is_string($h) && $h !== '' ? $h : '127.0.0.1';
}

/**
 * Initialize test database with fresh schema
 *
 * @param string $dbPath Path to the test database file
 *
 * @throws \PDOException
 * @throws \RuntimeException
 */
if (! function_exists('init_test_database')) {
    /**
     * Initialize test database with fresh schema
     *
     * @param string $dbPath Path to the test database file
     */
    function init_test_database(string $dbPath): void
    {
        require_once __DIR__ . '/../migrations.php';
        rebuild_sqlite_db($dbPath);
    }
}

/**
 * Perform a GET request via shared HTTP client
 *
 * @param string $path URL path (without base URI)
 * @param array<string, mixed> $queryParams Query parameters
 * @param array<string, string> $headers HTTP headers
 *
 * @return \Symfony\Contracts\HttpClient\ResponseInterface
 *
 * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
 * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
 * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
 * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
 */
function http_get(
    string $path,
    array $queryParams = [],
    array $headers = [],
): \Symfony\Contracts\HttpClient\ResponseInterface {
    static $httpClient = null;
    static $baseUrl = null;

    if ($baseUrl === null) {
        $baseUrl = \getenv('E2E_BASE_URL') ?: 'http://localhost:8080';
    }

    if ($httpClient === null) {
        $httpClient = \Symfony\Component\HttpClient\HttpClient::create(['base_uri' => $baseUrl]);
    }

    $url = $path;
    if (\count($queryParams) > 0) {
        $url .= '?' . http_build_query($queryParams);
    }

    return $httpClient->request('GET', $url, [
        'headers' => $headers,
    ]);
}

