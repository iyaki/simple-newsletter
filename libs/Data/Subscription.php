<?php

declare(strict_types=1);

namespace SimpleNewsletter\Data;

final readonly class Subscription
{
    public function __construct(
        public string $feedUri,
        public string $email,
        public bool $active = false,
    ) {
    }
}
