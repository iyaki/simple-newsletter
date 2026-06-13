<?php

declare(strict_types=1);

namespace SimpleNewsletter\Components;

use SimpleNewsletter\Templates\Email\EmailInterface;

interface Sender
{
    public function send(EmailInterface $template): void;
}
