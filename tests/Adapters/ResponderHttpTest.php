<?php

declare(strict_types=1);

use SimpleNewsletter\Adapters\ResponderHttp;
use SimpleNewsletter\Templates\ApiV1\HtmlResponse;
use SimpleNewsletter\Templates\ApiV1\JsonResponse;
use SimpleNewsletter\Templates\ApiV1\RedirectResponse;

test('sendResponse with JsonResponse echoes body', function () {
    $responder = new ResponderHttp();
    $response = JsonResponse::fromString('Test Title', 'Test Message');

    ob_start();
    $responder->sendResponse($response);
    $output = ob_get_clean();

    expect($output)->toBeString();
    $decoded = \json_decode($output, true);
    expect($decoded['title'])->toEqual('Test Title');
    expect($decoded['detail'])->toEqual('Test Message');
});

test('responseBuilderFromContentNegotiation with application/json returns builder that creates JsonResponse', function () {
    $responder = new ResponderHttp();
    $builder = $responder->responseBuilderFromContentNegotiation('application/json');
    $response = $builder->fromString('Title', 'Message');

    expect($response)->toBeInstanceOf(JsonResponse::class);
});

test('responseBuilderFromContentNegotiation with text/html returns builder that creates HtmlResponse', function () {
    $responder = new ResponderHttp();
    $builder = $responder->responseBuilderFromContentNegotiation('text/html');
    $response = $builder->fromString('Title', 'Message');

    expect($response)->toBeInstanceOf(HtmlResponse::class);
});

test('responseBuilderFromContentNegotiation with unsupported type defaults to JSON', function () {
    $responder = new ResponderHttp();
    $builder = $responder->responseBuilderFromContentNegotiation('application/xml');
    $response = $builder->fromString('Title', 'Message');

    expect($response)->toBeInstanceOf(JsonResponse::class);
});

test('responseBuilderFromRedirect returns builder that creates RedirectResponse', function () {
    $responder = new ResponderHttp();
    $builder = $responder->responseBuilderFromRedirect();
    $response = $builder->fromString('Title', 'Message', '/return');

    expect($response)->toBeInstanceOf(RedirectResponse::class);
});

test('sendResponse with non-ok non-redirect sets 400 status', function () {
    $responder = new ResponderHttp();
    $response = JsonResponse::fromString('Error', 'Bad request', false);

    ob_start();
    $responder->sendResponse($response);
    $output = ob_get_clean();

    expect($output)->toBeString();
    $decoded = \json_decode($output, true);
    expect($decoded['title'])->toEqual('Error');
});

test('content negotiation HTML builder fromEndUserException creates HtmlResponse', function () {
    $responder = new ResponderHttp();
    $e = new \SimpleNewsletter\Components\EndUserException('Something went wrong');
    $builder = $responder->responseBuilderFromContentNegotiation('text/html');
    $response = $builder->fromEndUserException($e);

    expect($response)->toBeInstanceOf(HtmlResponse::class);
    expect($response->isOk())->toBeFalse();
});

test('redirect builder fromEndUserException creates RedirectResponse with ok=false', function () {
    $responder = new ResponderHttp();
    $e = new \SimpleNewsletter\Components\EndUserException('Redirect error');
    $builder = $responder->responseBuilderFromRedirect();
    $response = $builder->fromEndUserException($e, 'https://example.com/back');

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->isOk())->toBeFalse();
});
