<?php

declare(strict_types=1);

namespace SimpleNewsletter\Models;

use Random\RandomException;
use SimpleNewsletter\Adapters\FeedImporterLaminas;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\FeedMetadata;
use SimpleNewsletter\Data\FeedsDAO;
use SimpleNewsletter\Data\Post;

final readonly class Feeds
{
    /**
     * Consecutive delivery fetch/parse failures after which a feed is
     * quarantined: skipped for a day between retry probes, so a permanently
     * broken feed costs one escalation instead of hourly error noise.
     */
    public const int QUARANTINE_THRESHOLD = 24;

    private const int QUARANTINE_RETRY_INTERVAL = 86_400;

    public function __construct(
        private FeedsDAO $feedsDAO,
        private FeedImporterLaminas $feedImporter,
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
        return $this->feedsDAO->getScheduled(
            $datetime,
            self::QUARANTINE_THRESHOLD,
            $datetime->getTimestamp() - self::QUARANTINE_RETRY_INTERVAL,
        );
    }

    /** @throws EndUserException */
    public function retrieveWithPosts(Feed $feed): Feed
    {
        $feed = $this->feedImporter->fetchWithPosts($feed);
        $this->feedsDAO->resetFetchFailures($feed->getUri());

        return $feed;
    }

    /**
     * Count one failed delivery fetch; the return is the new consecutive count.
     *
     * @throws EndUserException
     */
    public function recordFailedFetch(Feed $feed): int
    {
        return $this->feedsDAO->recordFailedFetch($feed->getUri(), new \DateTimeImmutable());
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
