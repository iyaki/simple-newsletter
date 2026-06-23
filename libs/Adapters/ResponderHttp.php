<?php

declare(strict_types=1);

namespace SimpleNewsletter\Adapters;

use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Templates\ApiV1\HtmlResponse;
use SimpleNewsletter\Templates\ApiV1\JsonResponse;
use SimpleNewsletter\Templates\ApiV1\RedirectResponse;
use SimpleNewsletter\Templates\ApiV1\ResponseBuilderInterface;
use SimpleNewsletter\Templates\ApiV1\ResponseInterface;

final class ResponderHttp
{
    private const string TYPE_HTML = 'text/html';

    private const string TYPE_JSON = 'application/json';

    public function sendResponse(ResponseInterface $response): void
    {
        $headers = $response->getHeaders();
        foreach ($headers as $key => $value) {
            \header(\sprintf('%s: %s', $key, $value));
        }

        if (! $response->isOk() && ! $response instanceof RedirectResponse) {
            \header(header: 'HTTP/1.0 400 Bad Request', replace: true, response_code: 400);
        }

        echo $response->getBody();
        \ob_flush();
        \flush();
    }

    public function responseBuilderFromContentNegotiation(string $acceptHeaderValueAsString): ResponseBuilderInterface
    {
        $acceptHeaderValues = \array_map(
            static fn (string $accept): string => \strtolower(\trim($accept)),
            \explode(',', $acceptHeaderValueAsString),
        );

        $compatibleTypes = \array_filter($acceptHeaderValues, static fn (string $accept): bool => \in_array(
            \trim($accept),
            [
                self::TYPE_HTML,
                self::TYPE_JSON,
            ],
            strict: true,
        ));

        $contentType = \reset($compatibleTypes);
        if ($contentType === false) {
            $contentType = self::TYPE_HTML;
        }

        return new readonly class($contentType) implements ResponseBuilderInterface {
            public function __construct(
                private string $contentType,
            ) {}

            #[\Override]
            public function fromString(
                string $title,
                string $message,
                ?string $return = null,
                bool $ok = true,
            ): ResponseInterface {
                return match ($this->contentType) {
                    'text/html' => HtmlResponse::fromString($title, $message, $return, $ok),
                    default => JsonResponse::fromString($title, $message, $ok),
                };
            }

            #[\Override]
            public function fromEndUserException(EndUserException $exception, ?string $return = null): ResponseInterface
            {
                return match ($this->contentType) {
                    'text/html' => HtmlResponse::fromEndUserException($exception),
                    default => JsonResponse::fromEndUserException($exception),
                };
            }
        };
    }

    public function responseBuilderFromRedirect(): ResponseBuilderInterface
    {
        return new class implements ResponseBuilderInterface {
            #[\Override]
            public function fromString(
                string $title,
                string $message,
                ?string $return = null,
                bool $ok = true,
            ): ResponseInterface {
                return RedirectResponse::fromString($title, $message, $return ?? '', $ok);
            }

            #[\Override]
            public function fromEndUserException(EndUserException $exception, ?string $return = null): ResponseInterface
            {
                return RedirectResponse::fromEndUserException($exception, $return ?? '');
            }
        };
    }
}
