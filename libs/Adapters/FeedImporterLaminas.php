<?php

declare(strict_types=1);

namespace SimpleNewsletter\Adapters;

use Laminas\Feed\Reader\Entry\EntryInterface;
use Laminas\Feed\Reader\Exception\RuntimeException as FeedException;
use Laminas\Feed\Reader\Feed\FeedInterface;
use Laminas\Feed\Reader\Reader;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\FeedMetadata;
use SimpleNewsletter\Data\Post;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final readonly class FeedImporterLaminas
{
    /** @throws EndUserException */
    public function fetchNew(string $uri): Feed
    {
        $sourceFeed = $this->import($uri);
        $metadata = new FeedMetadata(
            uri: $uri,
            title: $sourceFeed->getTitle() ?? '',
            link: self::safeLink($uri, $sourceFeed->getLink()),
            lastUpdate: new \DateTimeImmutable(),
        );
        return new Feed($metadata);
    }

    /** @throws EndUserException */
    public function fetch(Feed $feed): Feed
    {
        $sourceFeed = $this->import($feed->getUri());
        $metadata = new FeedMetadata(
            uri: $feed->getUri(),
            title: $sourceFeed->getTitle() ?? '',
            link: self::safeLink($feed->getUri(), $sourceFeed->getLink()),
            lastUpdate: new \DateTimeImmutable(),
        );
        return new Feed(metadata: $metadata, lastSentPostUri: $feed->lastSentPostUri);
    }

    /** @throws EndUserException */
    public function fetchWithPosts(Feed $feed): Feed
    {
        $sourceFeed = $this->import($feed->getUri());
        $posts = [];
        $config = new HtmlSanitizerConfig();
        $config = $config->allowSafeElements();

        $sanitizer = new HtmlSanitizer($config);
        foreach ($sourceFeed as $sourcePost) {
            $permalink = $sourcePost->getPermalink();
            if (! \is_string($permalink) || ! self::isHttpUri($permalink)) {
                // The permalink becomes the newsletter's primary click target
                // and nothing downstream validates its scheme (HTML-encoding
                // does not neutralize javascript:/data: URIs); entries
                // without a browser-safe http(s) URI are dropped.
                continue;
            }
            $cleanContent = $sanitizer->sanitize($sourcePost->getContent());
            $posts[] = new Post($permalink, $sourcePost->getTitle(), $cleanContent);
        }

        $metadata = new FeedMetadata(
            uri: $feed->getUri(),
            title: $sourceFeed->getTitle() ?? '',
            link: self::safeLink($feed->getUri(), $sourceFeed->getLink()),
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
        // Refuse non-public destinations before any network activity; the socket
        // adapter enforces the same policy on every redirect hop.
        PrivateAddressGuard::assertUriHostIsPublic($uri);

        // ponytail: one global client with a bounded, egress-checked adapter
        // (10MB / 60s per connection) so a hostile origin cannot exhaust worker
        // memory or time, nor reach internal targets across redirects.
        Reader::setHttpClient(new \Laminas\Http\Client(options: ['adapter' => BudgetedSocket::class]));

        try {
            return Reader::import($uri);
        } catch (FeedException $feedException) {
            $message = $feedException->getMessage();
            if (str_contains($message, '404')) {
                throw new EndUserException(
                    'The feed could not be loaded. Please check the URL and try again.',
                    0,
                    $feedException,
                );
            }

            if (str_contains($message, 'DOMDocument') || str_contains($message, 'XML')) {
                throw new EndUserException(
                    "This doesn't appear to be a valid RSS or Atom feed. Please verify the URL points to a valid feed.",
                    0,
                    $feedException,
                );
            }

            throw new EndUserException(
                'The feed could not be loaded. Please check the URL and try again.',
                0,
                $feedException,
            );
        } catch (\Laminas\Http\Client\Adapter\Exception\RuntimeException $adapterException) {
            // Connection refused, timeout, budget breach, refused destination:
            // collapse every transport failure into the generic load-failure so
            // the response does not reveal internal target state.
            throw new EndUserException(
                'The feed could not be loaded. Please check the URL and try again.',
                0,
                $adapterException,
            );
        } catch (\Laminas\Http\Exception\InvalidArgumentException $uriException) {
            throw new EndUserException('Invalid Feed URI', 0, $uriException);
        }
    }

    private static function isHttpUri(string $uri): bool
    {
        $scheme = \parse_url($uri, \PHP_URL_SCHEME);

        return \is_string($scheme) && \in_array(\strtolower($scheme), ['http', 'https'], strict: true);
    }

    /**
     * The publisher-declared channel link is rendered as a click target in
     * the confirmation email; when its scheme is not browser-safe, fall back
     * to the (egress-validated, http(s)) feed URI instead.
     */
    private static function safeLink(string $feedUri, ?string $declaredLink): string
    {
        if (\is_string($declaredLink) && $declaredLink !== '' && self::isHttpUri($declaredLink)) {
            return $declaredLink;
        }

        return $feedUri;
    }
}
