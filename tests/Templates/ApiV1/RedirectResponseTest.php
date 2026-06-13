<?php

declare(strict_types=1);

use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Templates\ApiV1\RedirectResponse;

test('fromString creates response with Location header', function () {
    $response = RedirectResponse::fromString('Done', 'Success', 'https://example.com/back');

    expect($response->getHeaders())->toHaveKey('Location');
});

test('fromEndUserException creates response with error title and ok false', function () {
    $exception = new EndUserException('Not found');
    $response = RedirectResponse::fromEndUserException($exception, 'https://example.com/back');

    expect($response->isOk())->toBeFalse();
    $headers = $response->getHeaders();
    expect($headers['Location'])->toContain('Error: Invalid data');
    expect($headers['Location'])->toContain('Not found');
});

test('isOk returns true for default fromString', function () {
    $response = RedirectResponse::fromString('Done', 'Success', 'https://example.com/back');

    expect($response->isOk())->toBeTrue();
});

test('fromEndUserException sets isOk to false', function () {
    $exception = new EndUserException('Error occurred');
    $response = RedirectResponse::fromEndUserException($exception, 'https://example.com/back');

    expect($response->isOk())->toBeFalse();
});

test('getBody returns empty string', function () {
    $response = RedirectResponse::fromString('Done', 'Success', 'https://example.com/back');

    expect($response->getBody())->toBe('');
});

test('Location header contains return URL with title message and ok params', function () {
    $response = RedirectResponse::fromString('Done', 'Success', 'https://example.com/back');

    $headers = $response->getHeaders();
    expect($headers['Location'])->toBe('https://example.com/back?title=Done&message=Success&ok=1');
});

test('fromEndUserException Location header contains ok=0', function () {
    $exception = new EndUserException('Missing field');
    $response = RedirectResponse::fromEndUserException($exception, 'https://example.com/back');

    $headers = $response->getHeaders();
    expect($headers['Location'])->toContain('ok=0');
});
