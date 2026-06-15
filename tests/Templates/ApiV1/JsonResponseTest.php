<?php

declare(strict_types=1);

use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Templates\ApiV1\JsonResponse;

test('fromString creates response with correct JSON body', function (): void {
    $response = JsonResponse::fromString('Success', 'All good');

    expect($response->isOk())->toBeTrue();
    $body = $response->getBody();
    expect(\json_decode($body, associative: true))->toBe([
        'title' => 'Success',
        'detail' => 'All good',
    ]);
});

test('fromEndUserException creates response with error title', function (): void {
    $exception = new EndUserException('Invalid value');
    $response = JsonResponse::fromEndUserException($exception);

    expect($response->isOk())->toBeFalse();
    $body = $response->getBody();
    /** @var array{title: string, detail: string} $decoded */
    $decoded = \json_decode($body, associative: true);
    expect($decoded['title'])->toBe('Error: Invalid data');
    expect($decoded['detail'])->toBe('Invalid value');
});

test('fromEndUserException sets isOk to false', function (): void {
    $exception = new EndUserException('Something failed');
    $response = JsonResponse::fromEndUserException($exception);

    expect($response->isOk())->toBeFalse();
});

test('getHeaders returns Content-Type application/json', function (): void {
    $response = JsonResponse::fromString('Test', 'Message');

    expect($response->getHeaders())->toBe([
        'Content-Type' => 'application/json',
    ]);
});

test('json_encode failure returns empty JSON object', function (): void {
    // Binary string that is not valid UTF-8 causes json_encode to fail
    $response = JsonResponse::fromString("\xfe\xff", 'test');

    $body = $response->getBody();
    expect($body)->toBe('{}');
});
