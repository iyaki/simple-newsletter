<?php

declare(strict_types=1);

namespace SimpleNewsletter\Components;

use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\Post;

interface FeedImporter
{
    public function fetchNew(string $uri): Feed;

    public function fetch(Feed $feed): Feed;

    public function fetchWithPosts(Feed $feed): Feed;

}
