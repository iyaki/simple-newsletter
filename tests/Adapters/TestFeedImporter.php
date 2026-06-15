<?php

declare(strict_types=1);

namespace Tests\Adapters;

use Laminas\Feed\Reader\Feed\FeedInterface;
use SimpleNewsletter\Adapters\FeedImporterLaminas;

final class TestFeedImporter extends FeedImporterLaminas
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
}