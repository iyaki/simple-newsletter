<?php

declare(strict_types=1);

use SimpleNewsletter\Adapters\ResponderHttp;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Templates\ApiV1\HtmlResponse;
use SimpleNewsletter\Templates\ApiV1\JsonResponse;
use SimpleNewsletter\Templates\ApiV1\RedirectResponse;

afterEach(function (): void {
    \header_remove();
});

test('sendResponse with JsonResponse echoes body', function (): void {
    $captured = '';
    \ob_start(function (string $buf) use (&$captured): string {
        $captured .= $buf;
        return '';
    });
    \ob_start();

    $responder = new ResponderHttp();
    $responder->sendResponse(JsonResponse::fromString('Test', 'Body'));

    \ob_end_clean();
    \ob_end_clean();

    expect($captured)->toContain('Test')->toContain('Body');
});

test('sendResponse with non-ok non-redirect sets 400 status', function (): void {
    $captured = '';
    \ob_start(function (string $buf) use (&$captured): string {
        $captured .= $buf;
        return '';
    });
    \ob_start();

    $responder = new ResponderHttp();
    $response = JsonResponse::fromEndUserException(new EndUserException('Error message'));
    $responder->sendResponse($response);

    \ob_end_clean();
    \ob_end_clean();

    expect(\http_response_code())->toBe(400);
    expect($captured)->toContain('Error message');
})->skip(!\function_exists('http_response_code'), 'http_response_code required');

test('responseBuilderFromContentNegotiation with application/json returns JsonResponse builder', function (): void {
    $responder = new ResponderHttp();
    $builder = $responder->responseBuilderFromContentNegotiation('application/json');
    expect($builder->fromString('T', 'M'))->toBeInstanceOf(JsonResponse::class);
});

test('responseBuilderFromContentNegotiation with text/html returns HtmlResponse builder', function (): void {
    $responder = new ResponderHttp();
    $builder = $responder->responseBuilderFromContentNegotiation('text/html');
    expect($builder->fromString('T', 'M'))->toBeInstanceOf(HtmlResponse::class);
});

test('responseBuilderFromContentNegotiation with unsupported type defaults to HtmlResponse', function (): void {
    $responder = new ResponderHttp();
    $builder = $responder->responseBuilderFromContentNegotiation('application/xml');
    expect($builder->fromString('T', 'M'))->toBeInstanceOf(HtmlResponse::class);
});

test('responseBuilderFromRedirect returns RedirectResponse builder', function (): void {
    $responder = new ResponderHttp();
    $builder = $responder->responseBuilderFromRedirect();
    $response = $builder->fromString('Done', 'Success', 'https://example.com/back');
    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->isOk())->toBeTrue();
});

test('content negotiation HTML builder fromEndUserException creates HtmlResponse', function (): void {
    $responder = new ResponderHttp();
    $builder = $responder->responseBuilderFromContentNegotiation('text/html');
    $response = $builder->fromEndUserException(new EndUserException('Fail'));
    expect($response)->toBeInstanceOf(HtmlResponse::class);
    expect($response->isOk())->toBeFalse();
});

test('redirect builder fromEndUserException creates RedirectResponse with ok=false', function (): void {
    $responder = new ResponderHttp();
    $builder = $responder->responseBuilderFromRedirect();
    $response = $builder->fromEndUserException(new EndUserException('Fail'), 'https://example.com/back');
    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->isOk())->toBeFalse();
});

