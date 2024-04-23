<?php

declare(strict_types=1);

namespace SimpleNewsletter\Models;

use Laminas\Feed\Reader\Reader;
use Laminas\Feed\Reader\ReaderImportInterface;
use SimpleNewsletter\Components\FeedImporter;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\FeedsDAO;
use SimpleNewsletter\Data\Post;

final readonly class Feeds
{
    public function __construct(
        private readonly FeedsDAO $feedsDAO,
        private readonly FeedImporter $feedImporter
    )
    {}

    public function retrieve(string $uri): Feed
    {
        $uri = trim($uri);
        $feed = $this->feedsDAO->find($uri);

        if ($feed instanceof Feed) {
            if ($feed->lastUpdate->diff(new \DateTimeImmutable())->days < 1) {
                return $feed;
            }

            $feed = $this->feedImporter->fetch($feed);
            $this->feedsDAO->update($feed);
            return $feed;
        }

        $feed = $this->feedImporter->fetchNew($uri);
        $this->feedsDAO->new($feed);
        return $feed;
    }

    /**
     * @return Feed[]
     */
    public function getSchedudled(\DateTimeImmutable $datetime): array
    {
        return $this->feedsDAO->getSchedudled($datetime);
    }

    public function retrieveWithPosts(Feed $feed): Feed
    {
        return $this->feedImporter->fetchWithPosts($feed);
    }

    public function updateLastSentPost(Feed $feed, Post $post): void
    {
        $updatedFeed = new Feed(
            $feed->uri,
            $feed->title,
            $feed->link,
            $feed->lastUpdate,
            $post->uri
        );
        $this->feedsDAO->update($updatedFeed);
    }

}
