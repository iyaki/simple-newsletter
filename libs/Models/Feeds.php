<?php

declare(strict_types=1);

namespace SimpleNewsletter\Models;

use Random\RandomException;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Components\FeedImporter;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\FeedMetadata;
use SimpleNewsletter\Data\FeedsDAO;
use SimpleNewsletter\Data\Post;

final readonly class Feeds
{
    public function __construct(
        private FeedsDAO $feedsDAO,
        private FeedImporter $feedImporter,
    ) {}

    /**
     * @throws EndUserException
     * @throws RandomException
     */
    public function retrieve(string $uri): Feed
    {
        $uri = trim($uri);
        $feed = $this->feedsDAO->find($uri);

        if ($feed instanceof Feed) {
            $days = (int) $feed->getLastUpdate()->diff(new \DateTimeImmutable())->days;
            if ($days < 1) {
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
     * @throws EndUserException
     */
    public function getScheduled(\DateTimeImmutable $datetime): array
    {
        return $this->feedsDAO->getScheduled($datetime);
    }

    /** @throws EndUserException */
    public function retrieveWithPosts(Feed $feed): Feed
    {
        return $this->feedImporter->fetchWithPosts($feed);
    }

    /** @throws EndUserException */
    public function updateLastSentPost(Feed $feed, Post $post): void
    {
        $updatedFeed = new Feed(
            metadata: new FeedMetadata(
                uri: $feed->getUri(),
                title: $feed->getTitle(),
                link: $feed->getLink(),
                lastUpdate: $feed->getLastUpdate(),
            ),
            lastSentPostUri: $post->uri,
        );
        $this->feedsDAO->update($updatedFeed);
    }
}
