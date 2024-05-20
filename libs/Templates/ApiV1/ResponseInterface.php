<?php

declare(strict_types=1);

namespace SimpleNewsletter\Templates\ApiV1;

use SimpleNewsletter\Components\EndUserException;

interface ResponseInterface
{
    public function getBody(): string;

    public function isOk(): bool;

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array;
}
