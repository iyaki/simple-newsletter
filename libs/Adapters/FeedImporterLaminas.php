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
use SimpleNewsletter\Data\FeedMetadata;
use SimpleNewsletter\Data\Post;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

readonly class FeedImporterLaminas implements FeedImporter
{
    /** @throws EndUserException */
    #[\Override]
    public function fetchNew(string $uri): Feed
    {
        $sourceFeed = $this->import($uri);
        $metadata = new FeedMetadata(
            uri: $uri,
            title: $sourceFeed->getTitle() ?? '',
            link: $sourceFeed->getLink() ?? '',
            lastUpdate: new \DateTimeImmutable(),
        );
        return new Feed($metadata);
    }

    /** @throws EndUserException */
    #[\Override]
    public function fetch(Feed $feed): Feed
    {
        $sourceFeed = $this->import($feed->getUri());
        $metadata = new FeedMetadata(
            uri: $feed->getUri(),
            title: $sourceFeed->getTitle() ?? '',
            link: $sourceFeed->getLink() ?? '',
            lastUpdate: new \DateTimeImmutable(),
        );
        return new Feed(metadata: $metadata, lastSentPostUri: $feed->lastSentPostUri);
    }

    /** @throws EndUserException */
    #[\Override]
    public function fetchWithPosts(Feed $feed): Feed
    {
        $sourceFeed = $this->import($feed->getUri());

        $posts = [];
        $sanitizer = new HtmlSanitizer(new HtmlSanitizerConfig()->allowSafeElements());
        foreach ($sourceFeed as $sourcePost) {
            $cleanContent = $sanitizer->sanitize($sourcePost->getContent());
            $posts[] = new Post($sourcePost->getPermalink(), $sourcePost->getTitle(), $cleanContent);
        }

        $metadata = new FeedMetadata(
            uri: $feed->getUri(),
            title: $sourceFeed->getTitle() ?? '',
            link: $sourceFeed->getLink() ?? '',
            lastUpdate: new \DateTimeImmutable(),
        );
        return new Feed(metadata: $metadata, lastSentPostUri: $feed->lastSentPostUri, posts: $posts);
    }

    /**
     * @return FeedInterface<EntryInterface>
     * @throws EndUserException
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
