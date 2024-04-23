<?php

declare(strict_types=1);

namespace SimpleNewsletter\Templates\Email;

interface EmailInterface
{
    /**
     * @return string[]
     */
    public function recipients(): array;

    public function subject(): string;

    public function body(): string;
}
