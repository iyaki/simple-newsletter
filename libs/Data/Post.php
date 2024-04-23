<?php

declare(strict_types=1);

namespace SimpleNewsletter\Data;

final readonly class Post
{
    public function __construct(
        public string $uri,
        public string $title,
        public string $content
    )
    {}

}
