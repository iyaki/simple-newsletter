<?php

declare(strict_types=1);

namespace SimpleNewsletter\Models;

use DateTimeImmutable;
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
        $uri = trim($uri);
        $feed = $this->feedsDAO->find($uri);

        if ($feed instanceof Feed && $feed->lastUpdate->diff(new DateTimeImmutable())->days < 1) {
            return $feed;
        }

        $importedFeed = Reader::import($uri);

        $feed = new Feed(
            $uri,
            $importedFeed->getTitle(),
            $importedFeed->getLink(),
            new DateTimeImmutable()
        );

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
