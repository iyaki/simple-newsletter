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

    /** @throws EndUserException when the scheduled-feed query itself fails */
    public function sendScheduled(\DateTimeImmutable $datetime): void
    {
        $scheduledFeeds = $this->feeds->getScheduled($datetime);

        foreach ($scheduledFeeds as $scheduledFeed) {
            try {
                $this->deliverFeed($scheduledFeed);
            } catch (\Throwable $feedFailure) {
                // Fetch/parse failures never reach here (handled inside
                // deliverFeed): these are operational failures — SMTP batch
                // aborts, DAO errors. ponytail: one broken feed must not
                // suppress delivery of the other co-scheduled feeds.
                ErrorReporter::report(
                    \sprintf(
                        'Skipping feed %s: %s',
                        $scheduledFeed->getUri(),
                        $feedFailure instanceof EndUserException ? $feedFailure->reason() : $feedFailure->getMessage(),
                    ),
                    $feedFailure,
                );
            }
        }
    }

    /** @throws EndUserException */
    private function deliverFeed(Feed $scheduledFeed): void
    {
        try {
            $feed = $this->feeds->retrieveWithPosts($scheduledFeed);
        } catch (\Throwable $fetchFailure) {
            $this->recordFetchFailure($scheduledFeed, $fetchFailure);

            return;
        }

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
     * Fetch/parse failures are external conditions (site moved, broken XML,
     * redirect to a private host), not app bugs: warning on the first
     * failure, one error at the quarantine threshold, silence after — the
     * daily retry probe stays invisible until the feed works again.
     *
     * @throws EndUserException when the failure counter itself cannot be written
     */
    private function recordFetchFailure(Feed $scheduledFeed, \Throwable $fetchFailure): void
    {
        $consecutiveFailures = $this->feeds->recordFailedFetch($scheduledFeed);

        if ($consecutiveFailures === 1) {
            ErrorReporter::expected(
                \sprintf('Feed %s failed to fetch or parse; retrying hourly: %s', $scheduledFeed->getUri(), $fetchFailure->getMessage()),
                $fetchFailure,
            );
        } elseif ($consecutiveFailures === Feeds::QUARANTINE_THRESHOLD) {
            ErrorReporter::report(
                \sprintf(
                    'Feed %s failed %d consecutive fetches; quarantined — retrying daily until it succeeds',
                    $scheduledFeed->getUri(),
                    $consecutiveFailures,
                ),
                $fetchFailure,
            );
        }
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
