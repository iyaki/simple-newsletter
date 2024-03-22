<?php

declare(strict_types=1);

namespace SimpleNewsletter\Data;

final readonly class Feed
{
    public ?object $lastPost;

    public function __construct(
        public string $uri,
        public string $title,
        public string $link,
        public \DateTimeImmutable $lastUpdate,
        ?string $lastPostUri = null,
        ?string $lastPostTitle = null,
    ) {
        if ($lastPostUri) {
            $this->lastPost = new readonly class($lastPostUri, $lastPostTitle) {
                public function __construct(
                    public string $uri,
                    public string $title,
                ) {
                }
            };
        } else {
            $this->lastPost = null;
        }
    }
}
