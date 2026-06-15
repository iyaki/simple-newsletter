<?php

declare(strict_types=1);

namespace SimpleNewsletter\Templates\ApiV1;

use SimpleNewsletter\Components\EndUserException;

/** @api */
interface ResponseBuilderInterface
{
    public function fromString(
        string $title,
        string $message,
        ?string $return = null,
        bool $ok = true,
    ): ResponseInterface;

    public function fromEndUserException(EndUserException $exception, ?string $return = null): ResponseInterface;
}
