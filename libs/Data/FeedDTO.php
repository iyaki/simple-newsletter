<?php

declare(strict_types=1);

namespace SimpleNewsletter\Data;

final readonly class FeedDTO
{
    public function __construct(
        public string $uri,
        public \DateTimeImmutable $lastUpdate,
        public string $lastPost
    ) {
    }
}
