<?php

declare(strict_types=1);

namespace SimpleNewsletter\Adapters;

use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Components\Responder;
use SimpleNewsletter\Templates\ApiV1\HtmlResponse;
use SimpleNewsletter\Templates\ApiV1\JsonResponse;
use SimpleNewsletter\Templates\ApiV1\RedirectResponse;
use SimpleNewsletter\Templates\ApiV1\ResponseInterface;

final class ResponderHttp
{
    private const TYPE_HTML = 'text/html';
    private const TYPE_JSON = 'application/json';

    public function sendResponse(ResponseInterface $response): void
    {
        $headers = $response->getHeaders();
        foreach ($headers as $key => $value) {
            \header(\sprintf('%s: %s', $key, $value));
        }

        if (! $response->isOk() && ! $response instanceof RedirectResponse) {
            \header('HTTP/1.0 400 Bad Request', true, 400);
        }

        echo $response->getBody();
    }

    public function responseBuilderFromContentNegotiation($acceptHeaderValue): object
    {
        $acceptHeaderValue = \strtolower($acceptHeaderValue);

        $compatibleTypes = \array_filter(
            \explode(',', $acceptHeaderValue),
            fn (string $accept): bool => \in_array(
                $accept,
                [
                    self::TYPE_HTML,
                    self::TYPE_JSON,
                ],
                true
            ),
        );

        $contentType = $compatibleTypes[0] ?? self::TYPE_JSON;

        return new class ($contentType) {

            public function __construct(private readonly string $contentType) {}

            public function fromString(string $title, string $message, string $return = null, bool $ok = true): ResponseInterface
            {
                return match ($this->contentType) {
                    'text/html' => HtmlResponse::fromString($title, $message, $return, $ok),
                    default => JsonResponse::fromString($title, $message, $ok),
                };
            }

            public function fromEndUserException(EndUserException $exception, string $return = null): ResponseInterface
            {
                return match ($this->contentType) {
                    'text/html' => HtmlResponse::fromEndUserException($exception),
                    default => JsonResponse::fromEndUserException($exception),
                };
            }
        };
    }

    public function responseBuilderFromRedirect(): object
    {
        return new class {
            public function fromString(string $title, string $message, string $return = null, bool $ok = true): ResponseInterface
            {
                return RedirectResponse::fromString($title, $message, $return, $ok);
            }

            public function fromEndUserException(EndUserException $exception, $return): ResponseInterface
            {
                return RedirectResponse::fromEndUserException($exception, $return);
            }
        };
    }

    private function checkURI(string $uri): void
    {
        if (! \filter_var($feedUri, \FILTER_VALIDATE_URL)) {
            throw new EndUserException('Invalid return URI');
        }
    }
}
