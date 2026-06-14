<?php

declare(strict_types=1);

namespace SimpleNewsletter\Data;

/**
 * Feed metadata
 */
final readonly class FeedMetadata
{
    public function __construct(
        public string $uri,
        public string $title,
        public string $link,
        public \DateTimeImmutable $lastUpdate,
    ) {}
}
