<?php

declare(strict_types=1);

namespace SimpleNewsletter\Adapters;

use Laminas\Feed\Reader\Entry\EntryInterface;
use Laminas\Feed\Reader\Exception\RuntimeException as FeedException;
use Laminas\Feed\Reader\Feed\FeedInterface;
use Laminas\Feed\Reader\Reader;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Components\FeedImporter;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\Post;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

readonly class FeedImporterLaminas implements FeedImporter
{
    #[\Override]
    public function fetchNew(string $uri): Feed
    {
        $sourceFeed = $this->import($uri);
        return new Feed($uri, $sourceFeed->getTitle() ?? '', $sourceFeed->getLink() ?? '', new \DateTimeImmutable());
    }

    #[\Override]
    public function fetch(Feed $feed): Feed
    {
        $sourceFeed = $this->import($feed->uri);
        return new Feed(
            $feed->uri,
            $sourceFeed->getTitle() ?? '',
            $sourceFeed->getLink() ?? '',
            new \DateTimeImmutable(),
            $feed->lastSentPostUri,
        );
    }

    #[\Override]
    public function fetchWithPosts(Feed $feed): Feed
    {
        $sourceFeed = $this->import($feed->uri);

        /** @var \Generator<int, Post> */
        $posts = (static function () use ($sourceFeed): \Generator {
            $sanitizer = new HtmlSanitizer(new HtmlSanitizerConfig()->allowSafeElements());
            foreach ($sourceFeed as $sourcePost) {
                $cleanContent = $sanitizer->sanitize($sourcePost->getContent());
                yield new Post(
                    $sourcePost->getPermalink() ?? $sourcePost->getLink(),
                    $sourcePost->getTitle(),
                    $cleanContent,
                );
            }
        })();

        return new Feed(
            $feed->uri,
            $sourceFeed->getTitle() ?? '',
            $sourceFeed->getLink() ?? '',
            new \DateTimeImmutable(),
            $feed->lastSentPostUri,
            $posts,
        );
    }

    /**
     * @return FeedInterface<EntryInterface>
     */
    private function import(string $uri): FeedInterface
    {
        try {
            return Reader::import($uri);
        } catch (FeedException $e) {
            $message = $e->getMessage();
            if (str_contains($message, '404')) {
                throw new EndUserException('The feed could not be loaded. Please check the URL and try again.', 0, $e);
            }

            if (str_contains($message, 'DOMDocument') || str_contains($message, 'XML')) {
                throw new EndUserException(
                    "This doesn't appear to be a valid RSS or Atom feed. Please verify the URL points to a valid feed.",
                    0,
                    $e,
                );
            }

            throw new EndUserException('The feed could not be loaded. Please check the URL and try again.', 0, $e);
        } catch (\Exception $networkException) {
            throw new EndUserException(
                'Could not connect to the feed server. Please verify the URL is correct.',
                0,
                $networkException,
            );
        }
    }
}
