<?php

declare(strict_types=1);

// Set test database path
$testDbPath = __DIR__ . '/../../data/test-e2e.db';
$_ENV['NEWSLETTER_DB_PATH'] = $testDbPath;
$_ENV['SECRET_KEY'] = 'test-e2e-secret-key-32chars!';
$_ENV['SERVER_NAME'] = 'http://localhost:8080';

require __DIR__ . '/../../vendor/autoload.php';

/**
 * Initialize test database with fresh schema
 *
 * @throws \PDOException
 */
if (! function_exists('initTestDatabase')) {
    function init_test_database(string $dbPath): void
    {
        if (\file_exists($dbPath)) {
            \unlink($dbPath);
        }

        $pdo = new \PDO("sqlite:{$dbPath}");
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Apply migrations in order
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
 * @param array<string, string> $queryParams
 * @param array<string, string> $headers
 */
function http_get(string $path, array $queryParams = [], array $headers = []): \Symfony\Contracts\HttpClient\ResponseInterface
{
    static $httpClient = null;
    static $baseUrl = 'http://localhost:8080';

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
 * Safely get response content without throwing on error status
 */
function get_content_safe(\Symfony\Contracts\HttpClient\ResponseInterface $response): string
{
    try {
        return $response->getContent();
    } catch (\Symfony\Component\HttpClient\Exception\ClientException) {
        // Fall through to try reflection
    } catch (\Exception) {
        return '';
    }

    try {
        $reflection = new \ReflectionClass($response);
        $property = $reflection->getProperty('body');
        return (string) $property->getValue($response);
    } catch (\Exception) {
        return '';
    }
}

/**
 * Safely get response as array without throwing on error status
 *
 * @return array<string, mixed>
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
