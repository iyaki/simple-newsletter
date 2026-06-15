<?php

declare(strict_types=1);

namespace SimpleNewsletter\Templates\Email;

/** @api */
interface EmailInterface
{
    public function recipient(): string;

    public function subject(): string;

    public function body(): string;
}
