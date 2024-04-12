<?php

declare(strict_types=1);

namespace SimpleNewsletter\Models;

use Laminas\Feed\Reader\Reader;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\FeedsDAO;

final class Feeds
{
    public function __construct(
        private readonly FeedsDAO $feedsDAO
    )
    {}

    public function retrieve(string $uri): Feed
    {
        $fetch = function (string $uri): Feed {
            $sourceFeed = Reader::import($uri);
            return new Feed(
                $uri,
                $sourceFeed->getTitle(),
                $sourceFeed->getLink(),
                new \DateTimeImmutable()
            );
        };

        $uri = trim($uri);
        $feed = $this->feedsDAO->find($uri);

        if ($feed instanceof Feed) {
            if ($feed->lastUpdate->diff(new \DateTimeImmutable())->days < 1) {
                return $feed;
            }

            $feed = $fetch($uri);
            $this->feedsDAO->update($feed);
            return $feed;
        }

        $feed = $fetch($uri);
        $this->feedsDAO->new($feed);
        return $feed;
    }

    /**
     * @codeCoverageIgnore
     */
    public function __clone()
    {
        throw new \Exception('Cloning this class is not allowed');
    }

    /**
     * @codeCoverageIgnore
     */
    public function __sleep()
    {
        throw new \Exception('This class can\'t be serialized');
    }
}
