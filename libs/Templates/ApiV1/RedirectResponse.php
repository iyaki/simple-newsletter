<?php

declare(strict_types=1);

namespace SimpleNewsletter\Templates\ApiV1;

use SimpleNewsletter\Components\EndUserException;

final class RedirectResponse implements ResponseInterface
{

    private function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly bool $ok,
        private readonly string $return,

    ) { }
    public function getBody(): string{
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

    static function fromString(string $title, string $message, bool $ok = true, $return = ''): static
    {
        return new static($title, $message, $ok, $return);
    }

    static function fromEndUserException(EndUserException $exception, string $return): static
    {
        return new static('Error: Invalid data', $exception->getMessage(), false, $return);
    }
}
