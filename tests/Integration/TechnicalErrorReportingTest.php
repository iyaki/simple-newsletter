<?php

declare(strict_types=1);

namespace Tests\Integration;

use Symfony\Component\Process\Process;

const TECHNICAL_ERROR_PORT = 9997;

/**
 * DB drift (e.g. deploying code whose migrations were not applied) surfaces as
 * a generic user-facing 400 from the DAO/RateLimiter PDOException wrappers.
 * The user envelope must stay sanitized, but the wrapped failure must reach
 * ErrorReporter (Sentry + error log) — a deploy like that must not be silent.
 */

/** @var Process|null $technicalErrorServer */
$technicalErrorServer = null;

/** @var string|null $technicalErrorLogFile */
$technicalErrorLogFile = null;

beforeAll(function () use (&$technicalErrorServer, &$technicalErrorLogFile): void {
    // Deliberately drifted schema: feeds/subscriptions exist but rate_limits is
    // missing, so RateLimiter::check() throws a wrapped PDOException before any
    // feed fetch — no feed server needed.
    $dbPath = \sys_get_temp_dir() . '/simple-newsletter-technical-error.db';
    require_once __DIR__ . '/../migrations.php';
    $pdo = rebuild_sqlite_db($dbPath);
    \assert($pdo instanceof \PDO);
    $pdo->exec('DROP TABLE rate_limits');
    $pdo = null;

    $technicalErrorLogFile = \sys_get_temp_dir() . '/simple-newsletter-technical-error.log';

    $technicalErrorServer = new Process(
        [
            'php',
            '-d', 'auto_prepend_file=' . __DIR__ . '/../../libs/bootstrap.php',
            '-d', 'error_log=' . $technicalErrorLogFile,
            '-S', '127.0.0.1:' . TECHNICAL_ERROR_PORT,
            '-t', __DIR__ . '/../../public',
        ],
        null,
        [
            'SECRET_KEY' => 'test-technical-error-secret-key-32',
            'NEWSLETTER_DB_PATH' => $dbPath,
            'PHP_CLI_SERVER_WORKERS' => '2',
            'PATH' => (string) \getenv('PATH'),
        ],
    );
    $technicalErrorServer->disableOutput();
    $technicalErrorServer->start();

    $errorCode = 0;
    $errorString = '';

    for ($i = 0; $i < 30; $i++) {
        \set_error_handler(static function (): bool {
            return true; // "connection refused" while the server boots is expected
        });
        try {
            $sock = \fsockopen('127.0.0.1', TECHNICAL_ERROR_PORT, $errorCode, $errorString, timeout: 1);
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

afterAll(function () use (&$technicalErrorServer, &$technicalErrorLogFile): void {
    $technicalErrorServer?->stop();
    if (\is_string($technicalErrorLogFile) && \file_exists($technicalErrorLogFile)) {
        \unlink($technicalErrorLogFile);
    }
});

test('wrapped DB failure keeps the sanitized 400 but reports the technical error', function () use (&$technicalErrorLogFile): void {
    $client = \Symfony\Component\HttpClient\HttpClient::create();

    $response = $client->request('GET', 'http://127.0.0.1:' . TECHNICAL_ERROR_PORT . '/v1/subscriptions/', [
        'query' => [
            'uri' => 'http://example.com/feed.xml',
            'email' => 'technical-error@example.com',
        ],
        'headers' => ['Accept' => 'application/json'],
    ]);

    $body = $response->getContent(false);

    // User envelope stays generic: no PDO details leak.
    expect($response->getStatusCode())->toBe(400)
        ->and($body)->toContain('Error: Invalid data')
        ->and($body)->toContain('A technical error occurred')
        ->and($body)->not->toContain('rate_limits');

    // Ops signal is reported (error log here; Sentry via captureException).
    $log = \is_string($technicalErrorLogFile) && \file_exists($technicalErrorLogFile)
        ? (string) \file_get_contents($technicalErrorLogFile)
        : '';
    expect($log)->toContain('Technical error:');
    expect($log)->toContain('no such table: rate_limits');
})->group('integration');
