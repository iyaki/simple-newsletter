<?php

declare(strict_types=1);

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

const BOOT_SMOKE_BASE_URI = 'http://localhost:8080';

/**
 * Attempt to detect if the dev server is running.
 */
function boot_smoke_server_reachable(HttpClientInterface $client): bool
{
    try {
        $response = $client->request('GET', BOOT_SMOKE_BASE_URI . '/', [
            'timeout' => 2,
        ]);
        $response->getStatusCode();

        return true;
    } catch (TransportExceptionInterface) {
        return false;
    }
}

test('HTTP entrypoints boot without fatal errors', function (): void {
    $client = HttpClient::create();

    if (! boot_smoke_server_reachable($client)) {
        test()->markTestSkipped(
            sprintf('Dev server not reachable at %s', BOOT_SMOKE_BASE_URI)
        );
    }

    $endpoints = [
        'landing page' => '/',
        'subscribe (valid params expected to fail validation, not crash)' => '/v1/subscriptions/?email=test@example.com&uri=https://example.com/feed',
        'confirmation' => '/v1/subscriptions/confirmation/?email=test@example.com&uri=https://example.com/feed&token=x',
        'cancellation' => '/v1/subscriptions/cancellation/?email=test@example.com&uri=https://example.com/feed&token=x',
    ];

    foreach ($endpoints as $label => $path) {
        try {
            $response = $client->request('GET', BOOT_SMOKE_BASE_URI . $path, [
                'timeout' => 5,
            ]);
            $status = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (TransportExceptionInterface $e) {
            test()->fail(sprintf(
                'Transport error for %s (%s): %s',
                $label,
                $path,
                $e->getMessage(),
            ));
        }

        expect($status)
            ->toBeGreaterThanOrEqual(200)
            ->toBeLessThan(500, sprintf(
                'Entrypoint "%s" (%s) returned %d — server-side error',
                $label,
                $path,
                $status,
            ));

        // FrankenPHP can return HTTP 200 even on fatal errors (Xdebug error page
        // embedded in the body). Check the body for fatal-error patterns.
        $fatalPatterns = [
            'Fatal error',
            'Class "' . preg_quote('SimpleNewsletter\Container', '/') . '" not found',
            'Parse error',
            'syntax error',
        ];
        foreach ($fatalPatterns as $pattern) {
            expect(\str_contains($content, $pattern))
                ->toBeFalse(sprintf(
                    'Entrypoint "%s" (%s) body contains fatal error pattern: %s',
                    $label,
                    $path,
                    $pattern,
                ));
        }
    }
});

test('serve images without errors', function (): void {
    $client = HttpClient::create();

    if (! boot_smoke_server_reachable($client)) {
        test()->markTestSkipped(
            sprintf('Dev server not reachable at %s', BOOT_SMOKE_BASE_URI)
        );
    }

    $assets = [
        'robots.txt' => '/robots.txt',
    ];

    foreach ($assets as $label => $path) {
        try {
            $response = $client->request('GET', BOOT_SMOKE_BASE_URI . $path, [
                'timeout' => 3,
            ]);
            $status = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            test()->fail(sprintf(
                'Transport error for %s (%s): %s',
                $label,
                $path,
                $e->getMessage(),
            ));
        }

        expect($status)->toBe(200);
    }
});
