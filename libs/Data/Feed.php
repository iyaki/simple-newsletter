<?php

declare(strict_types=1);

namespace SimpleNewsletter\Data;

final readonly class Feed
{
    /**
     * @param iterable<int, Post> $posts
     */
    public function __construct(
        public string $uri,
        public string $title,
        public string $link,
        public \DateTimeImmutable $lastUpdate,
        public ?string $lastSentPostUri = null,
        public iterable $posts = [],
    ) {
    }
}
