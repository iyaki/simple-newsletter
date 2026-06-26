<?php

declare(strict_types=1);

namespace SimpleNewsletter\Components;

use SimpleNewsletter\Data\Feed;

/**
 * Interface for fetching and parsing RSS/Atom feeds.
 *
 * Implementations handle feed retrieval, caching (24h TTL for unchanged feeds),
 * and incremental updates with new posts since last fetch.
 *
 * @api
 */
interface FeedImporter
{
    public function fetchNew(string $uri): Feed;

    public function fetch(Feed $feed): Feed;

    public function fetchWithPosts(Feed $feed): Feed;
}
