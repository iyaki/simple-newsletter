<?php

declare(strict_types=1);

namespace SimpleNewsletter\Models;

use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Components\ErrorReporter;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\SubscriptionsDAO;
use SimpleNewsletter\Data\Subscription;

/**
 * Hourly newsletter delivery workflow.
 *
 * Fetches every feed scheduled for the current hour and mails its new posts
 * to active subscribers. Feed-level and recipient-level failures are
 * quarantined so one broken feed or SMTP hiccup never suppresses or
 * duplicates the rest of the batch.
 */
final readonly class NewsletterDelivery
{
    public function __construct(
        private SubscriptionsDAO $subscriptionsDAO,
        private Feeds $feeds,
        private Newsletter $newsletter,
    ) {}

    /** @throws EndUserException */
    public function sendScheduled(\DateTimeImmutable $datetime): void
    {
        $scheduledFeeds = $this->feeds->getScheduled($datetime);

        foreach ($scheduledFeeds as $scheduledFeed) {
            try {
                $this->deliverFeed($scheduledFeed);
            } catch (\Throwable $feedFailure) {
                // ponytail: one broken feed must not suppress delivery of the
                // other co-scheduled feeds; quarantine, log + report, keep going.
                ErrorReporter::report(
                    \sprintf('Skipping feed %s: %s', $scheduledFeed->getUri(), $feedFailure->getMessage()),
                    $feedFailure,
                );
            }
        }
    }

    /** @throws EndUserException */
    private function deliverFeed(Feed $scheduledFeed): void
    {
        $feed = $this->feeds->retrieveWithPosts($scheduledFeed);

        $newPosts = $this->collectNewPosts($feed);

        if ($newPosts === []) {
            return;
        }

        /** @var list<Subscription> $activeSubscriptions */
        $activeSubscriptions = $this->subscriptionsDAO->findActiveSubscriptionsFor($feed);
        $this->newsletter->sendPostsToSubscribers($feed, $newPosts, ...$activeSubscriptions);

        // $newPosts is newest-first; advance the watermark to the newest sent.
        $this->feeds->updateLastSentPost($feed, $newPosts[0]);
    }

    /**
     * Posts arrive newest→oldest. Collect every post newer than the watermark
     * (lastSentPostUri); stop at it, since it and everything after were
     * already sent.
     *
     * Fail safe to newest-only when the delivery state is unknown: first
     * delivery (no watermark yet) and a watermark that vanished from the
     * publisher-controlled document (rolling window overflow or deliberate
     * drop) must not replay the historical backlog as one digest.
     *
     * @return list<\SimpleNewsletter\Data\Post>
     */
    private function collectNewPosts(Feed $feed): array
    {
        /** @var list<\SimpleNewsletter\Data\Post> $newPosts */
        $newPosts = [];
        $watermarkFound = false;
        foreach ($feed->posts as $post) {
            if ($post->uri === $feed->lastSentPostUri) {
                $watermarkFound = true;
                break;
            }
            $newPosts[] = $post;
        }

        if ($newPosts === []) {
            return [];
        }

        if ($feed->lastSentPostUri === null || ! $watermarkFound) {
            return [$newPosts[0]];
        }

        return $newPosts;
    }
}
