<?php

declare(strict_types=1);

// Set test environment variables
$testDbPath = __DIR__ . '/../../data/test-e2e.db';
putenv('NEWSLETTER_DB_PATH=' . $testDbPath);
putenv('SECRET_KEY=test-e2e-secret-key-32chars!');
putenv('SERVER_NAME=http://localhost:8080');
putenv('URI_SELF=http://localhost:8080');
// Disable Sentry for e2e tests
putenv('SENTRY_DSN=');

require __DIR__ . '/../../vendor/autoload.php';

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
     *
     * @throws \PDOException
     * @throws \RuntimeException
     */
    function init_test_database(string $dbPath): void
    {
        if (\file_exists($dbPath)) {
            // Database exists, clear data instead of recreating schema
            $pdo = new \PDO("sqlite:{$dbPath}");
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            try {
                $pdo->exec('DELETE FROM rate_limits');
            } catch (\PDOException) {
                // rate_limits table might not exist yet - ignore
            }
            $pdo->exec('DELETE FROM subscriptions');
            $pdo->exec('DELETE FROM feeds');
            return;
        }
        // Database doesn't exist, create fresh with migrations
        $pdo = new \PDO("sqlite:{$dbPath}");
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Apply migrations in order for new database
        $migrationsDir = __DIR__ . '/../../migrations';
        $migrationFiles = [
            '00-setup.sql',
            '01-feeds.sql',
            '02-subscriptions.sql',
            '03-rate-limiting.sql',
            '99-optimizations.sql',
        ];

        foreach ($migrationFiles as $file) {
            $sql = \file_get_contents($migrationsDir . '/' . $file);
            if ($sql === false) {
                continue;
            }
            $pdo->exec($sql);
        }
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
/**
 * Safely get status code without throwing on error status
 *
 * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
 */
function get_status_safe(\Symfony\Contracts\HttpClient\ResponseInterface $response): int
{
    try {
        return $response->getStatusCode();
    } catch (\Throwable $e) {
        // Return 0 on error for safe helper
        return 0;
    }
}

/**
 * Safely get headers without throwing on error status
 *
 * @return array<string, array<string>>
 *
 * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
 */
function get_headers_safe(\Symfony\Contracts\HttpClient\ResponseInterface $response): array
{
    try {
        return $response->getHeaders();
    } catch (\Throwable) {
        return [];
    }
}
function get_content_safe(\Symfony\Contracts\HttpClient\ResponseInterface $response): string
{
    try {
        return $response->getContent();

        // @mago-expect no-empty-catch-clause
    } catch (\Symfony\Component\HttpClient\Exception\ClientException) {
        // Fall through to try reflection
    } catch (\Exception $e) {
        // Intentionally silenced - get_content_safe should never throw
        return '';
    }

    try {
        $reflection = new \ReflectionClass($response);
        $property = $reflection->getProperty('body');
        /** @var string $body */
        return $property->getValue($response);
    } catch (\Exception) {
        return '';
    }
}

/**
 * Safely get response as array without throwing on error status
 *
 * @return array<string, mixed>
 *
 * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
 * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
 * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
 * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
 */
function to_array_safe(\Symfony\Contracts\HttpClient\ResponseInterface $response): array
{
    $content = get_content_safe($response);
    if (\strlen($content) === 0) {
        return [];
    }

    /** @var array<string, mixed>|null $decoded */
    $decoded = \json_decode($content, associative: true);
    return \is_array($decoded) ? $decoded : [];
}
