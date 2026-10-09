<?php

declare(strict_types=1);

namespace Tests\Integration;
/**
 * Fail-closed configuration coverage: boots the real subscribe entrypoint in
 * a sub-process php -S server with an EMPTY SECRET_KEY and asserts the
 * misconfigured deployment renders a 500 (JSON envelope under content
 * negotiation), not a forgeable-token success or a mislabeled 400.
 */

use Symfony\Component\Process\Process;

const FAIL_CLOSED_PORT = 9996;

/** @var Process|null $failClosedServer */
$failClosedServer = null;

beforeAll(function () use (&$failClosedServer): void {
    // Fresh schema so the rate limiter (which runs before the configuration
    // check) does not short-circuit with a missing-table technical error.
    $dbPath = \sys_get_temp_dir() . '/simple-newsletter-fail-closed.db';
    foreach ([$dbPath, $dbPath . '-wal', $dbPath . '-shm'] as $file) {
        if (\file_exists($file)) {
            \unlink($file);
        }
    }
    $pdo = new \PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $files = \glob(__DIR__ . '/../../migrations/*.sql');
    if ($files !== false) {
        \sort($files);
        foreach ($files as $file) {
            $pdo->exec((string) \file_get_contents($file));
        }
    }
    $pdo = null;

    $failClosedServer = new Process(
        [
            'php',
            '-d', 'auto_prepend_file=' . __DIR__ . '/../../libs/bootstrap.php',
            '-S', '127.0.0.1:' . FAIL_CLOSED_PORT,
            '-t', __DIR__ . '/../../public',
        ],
        null,
        [
            // Symfony Process merges this env over the parent's, so an
            // inherited SECRET_KEY must be forced empty: the entrypoint must
            // fail closed on a misconfigured deployment.
            'SECRET_KEY' => '',
            'NEWSLETTER_DB_PATH' => $dbPath,
            'PHP_CLI_SERVER_WORKERS' => '2',
            'PATH' => (string) \getenv('PATH'),
        ],
    );
    $failClosedServer->disableOutput();
    $failClosedServer->start();

    $errorCode = 0;
    $errorString = '';

    for ($i = 0; $i < 30; $i++) {
        \set_error_handler(static function (): bool {
            return true; // "connection refused" while the server boots is expected
        });
        try {
            $sock = \fsockopen('127.0.0.1', FAIL_CLOSED_PORT, $errorCode, $errorString, timeout: 1);
        } finally {
            \restore_error_handler();
        }
        if (\is_resource($sock)) {
            \fclose($sock);
            break;
        }
        \usleep(100_000);
    }
});

afterAll(function () use (&$failClosedServer): void {
    $failClosedServer?->stop();
});

test('subscribe endpoint fails closed with a 500 JSON envelope when SECRET_KEY is empty', function (): void {
    $client = \Symfony\Component\HttpClient\HttpClient::create();

    $response = $client->request('GET', 'http://127.0.0.1:' . FAIL_CLOSED_PORT . '/v1/subscriptions/', [
        'query' => [
            'uri' => 'http://example.com/feed.xml',
            'email' => 'fail-closed@example.com',
        ],
        'headers' => ['Accept' => 'application/json'],
    ]);

    $headers = $response->getHeaders(false);

    expect($response->getStatusCode())->toBe(500);
    expect($headers['content-type'][0] ?? '')->toContain('application/json');
    expect($response->getContent(false))->toContain('Error: Internal server error');
    expect($response->getContent(false))->toContain('A technical error occurred');
})->group('integration');
