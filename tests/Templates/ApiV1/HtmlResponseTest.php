<?php

declare(strict_types=1);

use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Templates\ApiV1\HtmlResponse;

test('fromString creates response with given title and message', function () {
    $response = HtmlResponse::fromString('Welcome', 'You are subscribed');

    expect($response->isOk())->toBeTrue();
    $body = $response->getBody();
    expect($body)->toContain('Welcome');
    expect($body)->toContain('You are subscribed');
});

test('fromEndUserException creates response with error title and exception message', function () {
    $exception = new EndUserException('Invalid email address');
    $response = HtmlResponse::fromEndUserException($exception);

    expect($response->isOk())->toBeFalse();
    $body = $response->getBody();
    expect($body)->toContain('Error: Invalid data');
    expect($body)->toContain('Invalid email address');
});

test('getHeaders returns Content-Type text/html', function () {
    $response = HtmlResponse::fromString('Title', 'Message');

    expect($response->getHeaders())->toBe([
        'Content-Type' => 'text/html',
    ]);
});

test('fromString with return includes redirect link in body', function () {
    $response = HtmlResponse::fromString('Done', 'Success', 'https://example.com/back');

    $body = $response->getBody();
    expect($body)->toContain('https://example.com/back');
    expect($body)->toContain('Return to');
});

test('fromString with ok=false sets isOk to false', function () {
    $response = HtmlResponse::fromString('Error', 'Something went wrong', null, false);

    expect($response->isOk())->toBeFalse();
    $body = $response->getBody();
    expect($body)->toContain('Error');
    expect($body)->toContain('Something went wrong');
});
