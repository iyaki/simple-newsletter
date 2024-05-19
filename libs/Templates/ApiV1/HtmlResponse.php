<?php

declare(strict_types=1);

namespace SimpleNewsletter\Templates\ApiV1;

use SimpleNewsletter\Components\EndUserException;

final class HtmlResponse implements ResponseInterface
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
        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <title>Simple Newsletter</title>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="author" content="Ivan Yaki">
            <meta name="description" content="Simple Atom and RSS feeds to newsletter subscription service">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/water.css">
            <link
                rel="icon"
                href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📨</text></svg>"
            />
        </head>
        <body>
            <main>
                <h1 style="text-align: center; margin-top: 1em; margin-bottom: 2em;">Simple Newsletter</h1>
                <h2 style="text-align: center;">{$this->title}</h2>
                <p style="text-align: center;">{$this->message}</p>
            </main>
        </body>
        </html>
        HTML;
    }

    public function isOk(): bool
    {
        return $this->ok;
    }

    public function getHeaders(): array
    {
        return [
            'Content-Type' => 'text/html',
        ];
    }
}
