<?php

declare(strict_types=1);

namespace SimpleNewsletter\Data;

/**
 * Feed data with optional posts
 */
final readonly class Feed
{
    /**
     * @param iterable<int, Post> $posts
     */
    public function __construct(
        public FeedMetadata $metadata,
        public ?string $lastSentPostUri = null,
        public iterable $posts = [],
    ) {}

    public function getUri(): string
    {
        return $this->metadata->uri;
    }

    public function getTitle(): string
    {
        return $this->metadata->title;
    }

    public function getLink(): string
    {
        return $this->metadata->link;
    }

    public function getLastUpdate(): \DateTimeImmutable
    {
        return $this->metadata->lastUpdate;
    }

    public function getLastSentPostUri(): ?string
    {
        return $this->lastSentPostUri;
    }
}
