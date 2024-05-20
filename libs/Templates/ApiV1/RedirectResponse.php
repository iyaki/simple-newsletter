<?php

declare(strict_types=1);

namespace SimpleNewsletter\Templates\ApiV1;

use SimpleNewsletter\Components\EndUserException;

final class RedirectResponse implements ResponseInterface
{

    private function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly string $return,
        private readonly bool $ok

    ) { }
    public function getBody(): string
    {
        return '';
    }

    public function isOk(): bool
    {
        return $this->ok;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [
            'Location' => \sprintf(
                '%s?title=%s&message=%s&ok=%u',
                $this->return,
                $this->title,
                $this->message,
                (int) $this->ok
            ),
        ];
    }

    static function fromString(string $title, string $message, string $return, bool $ok = true): static
    {
        return new static($title, $message, $return, $ok);
    }

    static function fromEndUserException(EndUserException $exception, string $return): static
    {
        return new static('Error: Invalid data', $exception->getMessage(), $return, false);
    }
}
