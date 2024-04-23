<?php

declare(strict_types=1);

namespace SimpleNewsletter\Adapters;

use Laminas\Feed\Reader\Reader;
use SimpleNewsletter\Components\FeedImporter;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\Post;

final readonly class FeedImporterLaminas implements FeedImporter
{
    public function fetchNew(string $uri): Feed
    {
        $sourceFeed = Reader::import($uri);
        return new Feed(
            $uri,
            $sourceFeed->getTitle(),
            $sourceFeed->getLink(),
            new \DateTimeImmutable()
        );
    }

    public function fetch(Feed $feed): Feed
    {
        $sourceFeed = Reader::import($uri);
        return new Feed(
            $feed->uri,
            $sourceFeed->getTitle(),
            $sourceFeed->getLink(),
            new \DateTimeImmutable(),
            $feed->lastSentPostUri
        );
    }

    public function fetchWithPosts(Feed $feed): Feed
    {
        $sourceFeed = Reader::import($feed->uri);

        /** @var \Generator<int, Post> */
        $posts = (function () use ($sourceFeed): \Generator {
            foreach ($sourceFeed as $sourcePost) {
                yield new Post(
                    $sourcePost->getPermalink() ?: $sourcePost->getLink(),
                    $sourcePost->getTitle(),
                    $sourcePost->getContent()
                );
            }
        })();

        return new Feed(
            $feed->uri,
            $sourceFeed->getTitle(),
            $sourceFeed->getLink(),
            new \DateTimeImmutable(),
            $feed->lastSentPostUri,
            $posts
        );
    }
}
