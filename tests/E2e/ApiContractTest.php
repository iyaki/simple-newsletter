<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/** @throws \Exception */
it('returns HTML content type by default', function (): void {
    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     */
    $response = http_get('/v1/subscriptions/', [
        'uri' => 'http://127.0.0.1:9995/valid.xml',
        'email' => 'test@example.com',
    ]);

    expect($response->getStatusCode())->toBe(200);
    $headers = $response->getHeaders();
    \assert(\array_key_exists('content-type', $headers), 'response should have content-type header');
    expect($headers['content-type'][0] ?? '')->toContain('text/html');
});

/** @throws \Exception */
it('handles missing required parameters', function (): void {
    /**
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    $response = http_get('/v1/subscriptions/', [
        // Both uri and email missing
    ]);

    expect($response->getStatusCode())->toBe(400);
});

