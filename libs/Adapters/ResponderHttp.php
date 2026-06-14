<?php

declare(strict_types=1);

namespace SimpleNewsletter\Adapters;

use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Templates\ApiV1\HtmlResponse;
use SimpleNewsletter\Templates\ApiV1\JsonResponse;
use SimpleNewsletter\Templates\ApiV1\RedirectResponse;
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
            \header('HTTP/1.0 400 Bad Request', true, 400);
        }

        echo $response->getBody();
    }

    public function responseBuilderFromContentNegotiation(string $acceptHeaderValueAsString): object
    {
        $acceptHeaderValues = \array_map(
            static fn (string $accept): string => \strtolower(\trim($accept)),
            \explode(',', $acceptHeaderValueAsString)
        );

        $compatibleTypes = \array_filter(
            $acceptHeaderValues,
            static fn (string $accept): bool => \in_array(
                \trim($accept),
                [
                    self::TYPE_HTML,
                    self::TYPE_JSON,
                ],
                true
            ),
        );

        $contentType = \reset($compatibleTypes);
        $contentType = $contentType ?: self::TYPE_JSON;

        return new readonly class ($contentType) {

            public function __construct(private string $contentType) {}

            public function fromString(string $title, string $message, ?string $return = null, bool $ok = true): ResponseInterface
            {
                return match ($this->contentType) {
                    'text/html' => HtmlResponse::fromString($title, $message, $return, $ok),
                    default => JsonResponse::fromString($title, $message, $ok),
                };
            }

            public function fromEndUserException(EndUserException $exception, ?string $return = null): ResponseInterface
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
            public function fromString(string $title, string $message, ?string $return = null, bool $ok = true): ResponseInterface
            {
            return RedirectResponse::fromString($title, $message, $return ?? '', $ok);
            }

            public function fromEndUserException(EndUserException $exception, ?string $return): ResponseInterface
            {
            return RedirectResponse::fromEndUserException($exception, $return ?? '');
            }
        };
    }

}
