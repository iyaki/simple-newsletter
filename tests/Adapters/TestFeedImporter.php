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

    protected function import(string $uri): FeedInterface
    {
        $feed = $this->mockFeed;
        \assert(condition: $feed !== null, description: 'Mock feed must be set before calling import');

        return $feed;
    }
}
