<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Tests\E2e\HttpClientHelpers;

uses(HttpClientHelpers::class);

it('returns HTML content type by default', function () {
    $response = self::get('/v1/subscriptions/', [
        'uri' => 'https://example.com/feed.xml',
        'email' => 'test@example.com',
    ]);

    expect($response->getStatusCode())->toBe(200);
    expect($response->getHeaders()['content-type'][0])->toContain('text/html');
});

it('handles missing required parameters', function () {
    $response = self::get('/v1/subscriptions/', [
        // Both uri and email missing
    ]);

    expect($response->getStatusCode())->toBe(400);
});

it('returns 404 for unknown routes', function () {
    $response = self::get('/nonexistent-endpoint');

    expect($response->getStatusCode())->toBe(404);
});