<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

it('returns HTML content type by default', function (): void {
    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     */
    $response = http_get('/v1/subscriptions/', [
        'uri' => 'https://example.com/feed.xml',
        'email' => 'test@example.com',
    ]);

    expect($response->getStatusCode())->toBe(200);
    $headers = $response->getHeaders();
    assert(isset($headers['content-type']));
    expect($headers['content-type'][0] ?? '')->toContain('text/html');
});

it('handles missing required parameters', function (): void {
    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    $response = http_get('/v1/subscriptions/', [
        // Both uri and email missing
    ]);

    expect($response->getStatusCode())->toBe(400);
});

it('returns 404 for unknown routes', function (): void {
    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    $response = http_get('/nonexistent-endpoint');

    expect($response->getStatusCode())->toBe(404);
});
