<?php

declare(strict_types=1);

namespace Tests\Adapters;

use Laminas\Feed\Reader\Feed\FeedInterface;
use SimpleNewsletter\Components\FeedImporter;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\FeedMetadata;
use SimpleNewsletter\Data\Post;

final class TestFeedImporter implements FeedImporter
{
    private ?FeedInterface $mockFeed = null;

    public function setMockFeed(FeedInterface $feed): void
    {
        $this->mockFeed = $feed;
    }

    /**
     * @throws \RuntimeException
     */
    protected function _import(string $_uri): FeedInterface
    {
        if ($this->mockFeed === null) {
            throw new \RuntimeException('Mock feed not set');
        }

        return $this->mockFeed;
    }

    /**
     * @throws \RuntimeException
     */
    #[\Override]
    public function fetchNew(string $uri): Feed
    {
        $laminasFeed = $this->_import($uri);

        $link = $laminasFeed->getLink();
        $title = $laminasFeed->getTitle();
        $desc = $laminasFeed->getDescription();

        $metadata = new FeedMetadata(
            $link ?? $uri,
            $title ?: 'Untitled',
            $desc ?: $uri,
            new \DateTimeImmutable(),
        );

        return new Feed($metadata);
    }

    /**
     * @throws \RuntimeException
     */
    #[\Override]
    public function fetch(Feed $feed): Feed
    {
        return $this->fetchNew($feed->metadata->uri);
    }

    /**
     * @throws \RuntimeException
     */
    #[\Override]
    public function fetchWithPosts(Feed $feed): Feed
    {
        $laminasFeed = $this->_import($feed->metadata->uri);

        $link = $laminasFeed->getLink();
        $title = $laminasFeed->getTitle();
        $desc = $laminasFeed->getDescription();

        $metadata = new FeedMetadata(
            $link ?? $feed->metadata->uri,
            $title ?: $feed->metadata->title,
            $desc ?: $feed->metadata->link,
            new \DateTimeImmutable(),
        );

        $posts = [];
        foreach ($laminasFeed as $entry) {
            \assert($entry instanceof \Laminas\Feed\Reader\Entry\EntryInterface);
            $entryLink = $entry->getLink();
            $entryTitle = $entry->getTitle();
            $entryContent = $entry->getContent();
            $entryDescription = $entry->getDescription();

            /** @var string $content */
            $content = $entryContent ?: ($entryDescription ?: '');

            $posts[] = new Post(
                $entryLink,
                $entryTitle ?: 'Untitled',
                $content,
            );
        }

        return new Feed($metadata, null, $posts);
    }
}