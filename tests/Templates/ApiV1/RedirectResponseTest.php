<?php

declare(strict_types=1);

use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Templates\ApiV1\RedirectResponse;

test('fromString creates response with Location header', function (): void {
    $response = RedirectResponse::fromString('Done', 'Success', 'https://example.com/back');

    expect($response->getHeaders())->toHaveKey('Location');
});

test('fromEndUserException creates response with error title and ok false', function (): void {
    $exception = new EndUserException('Not found');
    $response = RedirectResponse::fromEndUserException($exception, 'https://example.com/back');

    expect($response->isOk())->toBeFalse();
    $headers = $response->getHeaders();
    \assert(\array_key_exists('Location', $headers), 'Location header should exist');
});

test('isOk returns true for default fromString', function (): void {
    $response = RedirectResponse::fromString('Done', 'Success', 'https://example.com/back');

    expect($response->isOk())->toBeTrue();
});

test('fromEndUserException sets isOk to false', function (): void {
    $exception = new EndUserException('Error occurred');
    $response = RedirectResponse::fromEndUserException($exception, 'https://example.com/back');

    expect($response->isOk())->toBeFalse();
});

test('getBody returns empty string', function (): void {
    $response = RedirectResponse::fromString('Done', 'Success', 'https://example.com/back');

    expect($response->getBody())->toBe('');
});

test('Location header contains return URL with title message and ok params', function (): void {
    $response = RedirectResponse::fromString('Done', 'Success', 'https://example.com/back');

    $headers = $response->getHeaders();
    \assert(\array_key_exists('Location', $headers), 'Location header should exist');
    expect($headers['Location'])->toBe('https://example.com/back?title=Done&message=Success&ok=1');
});

test('fromEndUserException Location header contains ok=0', function (): void {
    $exception = new EndUserException('Missing field');
    $response = RedirectResponse::fromEndUserException($exception, 'https://example.com/back');

    $headers = $response->getHeaders();
    \assert(\array_key_exists('Location', $headers), 'Location header should exist');
    expect($headers['Location'])->toContain('ok=0');
});


