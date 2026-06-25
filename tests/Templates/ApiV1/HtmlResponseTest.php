<?php

declare(strict_types=1);

use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Templates\ApiV1\HtmlResponse;

test('fromString creates response with correct HTML body', function (): void {
    $response = HtmlResponse::fromString('Test Title', 'Test message');
    expect($response->isOk())->toBeTrue();
    $body = $response->getBody();
    expect($body)->toContain('Test Title')->toContain('Test message');
});

test('fromEndUserException creates response with error title and message', function (): void {
    $exception = new EndUserException('Invalid value');
    $response = HtmlResponse::fromEndUserException($exception);
    expect($response->isOk())->toBeFalse();
    $body = $response->getBody();
    expect($body)->toContain('Error: Invalid data')->toContain('Invalid value');
});

test('getHeaders returns Content-Type text/html', function (): void {
    $response = HtmlResponse::fromString('Title', 'Message');
    expect($response->getHeaders())->toBe(['Content-Type' => 'text/html']);
});

test('fromString with return includes redirect link in body', function (): void {
    $response = HtmlResponse::fromString('Done', 'Success', 'https://example.com/back');
    $body = $response->getBody();
    expect($body)->toContain('https://example.com/back')->toContain('href=');
});

test('fromString with ok=false sets isOk to false', function (): void {
    $response = HtmlResponse::fromString('Error', 'Failed', null, false);
    expect($response->isOk())->toBeFalse();
});

test('fromString with ok=true sets isOk to true', function (): void {
    $response = HtmlResponse::fromString('OK', 'All good', null, true);
    expect($response->isOk())->toBeTrue();
});

covers(SimpleNewsletter\Templates\ApiV1\HtmlResponse::class);
