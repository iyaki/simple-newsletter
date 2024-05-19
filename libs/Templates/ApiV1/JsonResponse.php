<?php

declare(strict_types=1);

namespace SimpleNewsletter\Templates\ApiV1;

use SimpleNewsletter\Components\EndUserException;

final class JsonResponse implements ResponseInterface
{
    private function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly bool $ok
    ) { }

    static public function fromString(string $title, string $message, bool $ok = true): static
    {
        return new static($title, $message, $ok);
    }

    static public function fromEndUserException(EndUserException $exception): static
    {
        return new static('Error: Invalid data', $exception->getMessage(), false);
    }

    public function getBody(): string
    {
        return \json_encode([
            'title' => $this->title,
            'detail' => $this->message,
        ]);
    }

    public function isOk(): bool
    {
        return $this->ok;
    }

    public function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
        ];
    }
}
