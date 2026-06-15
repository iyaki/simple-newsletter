<?php

declare(strict_types=1);

namespace SimpleNewsletter\Components;

use SimpleNewsletter\Templates\Email\EmailInterface;

/** @api */
interface Sender
{
    public function send(EmailInterface $template): void;
}
