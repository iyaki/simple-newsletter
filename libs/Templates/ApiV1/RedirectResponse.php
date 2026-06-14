<?php

declare(strict_types=1);

namespace SimpleNewsletter\Templates\ApiV1;

use SimpleNewsletter\Components\EndUserException;

final readonly class RedirectResponse implements ResponseInterface
{

    private function __construct(
        private string $title,
        private string $message,
        private string $return,
        private bool $ok

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

    public static function fromString(string $title, string $message, string $return, bool $ok = true): static
    {
        return new self($title, $message, $return, $ok);
    }

    public static function fromEndUserException(EndUserException $exception, string $return): static
    {
        return new self('Error: Invalid data', $exception->getMessage(), $return, false);
    }
}
