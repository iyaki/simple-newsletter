<?php

declare(strict_types=1);

namespace SimpleNewsletter\Models;

use Random\RandomException;
use SimpleNewsletter\Components\Auth;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\Subscription;
use SimpleNewsletter\Data\SubscriptionsDAO;

/**
 * Manages newsletter subscriptions and delivery workflows.
 *
 * Orchestrates the double-opt-in subscription flow, confirmation token validation,
 * cancellation, and scheduled newsletter delivery to confirmed subscribers.
 */
final readonly class Subscriptions
{
    public function __construct(
        private SubscriptionsDAO $subscriptionsDAO,
        private Feeds $feeds,
        private Newsletter $newsletter,
        private Auth $auth,
    ) {}

    /** @throws EndUserException|RandomException */
    public function add(string $feedUri, string $email): void
    {
        if (! \filter_var($feedUri, \FILTER_VALIDATE_URL)) {
            throw new EndUserException('Invalid Feed URI');
        }

        $feed = $this->feeds->retrieve($feedUri);

        if (! \filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new EndUserException('Invalid email address');
        }

        $subscription = $this->subscriptionsDAO->find($feedUri, $email);

        if ($subscription instanceof Subscription) {
            if ($subscription->active) {
                throw new EndUserException('You are already subscribed to this feed.');
            }
        } else {
            $subscription = new Subscription($feedUri, $email, tokenNonce: $this->newTokenNonce());
            $this->subscriptionsDAO->new($subscription);
        }

        $this->newsletter->sendConfirmation($feed, $subscription);
    }

    /** @throws EndUserException */
    public function confirm(string $feedUri, string $email, #[\SensitiveParameter] string $token): void
    {
        $subscription = $this->subscriptionsDAO->find($feedUri, $email);

        if (! $subscription instanceof Subscription) {
            throw new EndUserException('Subscription not found. The link may be invalid or expired.');
        }

        if (! $this->auth->verify($this->tokenKey('confirm', $feedUri, $email, $subscription->tokenNonce), $token)) {
            throw new EndUserException('Invalid token. Please check your confirmation link and try again.');
        }

        $this->subscriptionsDAO->activate($subscription);
    }

    /** @throws EndUserException */
    public function cancel(string $feedUri, string $email, #[\SensitiveParameter] string $token): void
    {
        $subscription = $this->subscriptionsDAO->find($feedUri, $email);
        if (! $subscription instanceof Subscription) {
            throw new EndUserException('Subscription not found');
        }

        if (! $this->auth->verify($this->tokenKey('cancel', $feedUri, $email, $subscription->tokenNonce), $token)) {
            throw new EndUserException('Invalid token. Please check your cancellation link and try again.');
        }

        $this->subscriptionsDAO->delete($subscription);
    }

    /**
     * The MAC input binds the action, the feed and the per-subscription nonce,
     * so one leaked link cannot act on another feed, trigger the opposite
     * action, or be replayed against rows created after it leaked.
     */
    private function tokenKey(string $action, string $feedUri, string $email, string $nonce): string
    {
        return $action . '|' . $feedUri . '|' . $email . '|' . $nonce;
    }

    /** @throws RandomException */
    private function newTokenNonce(): string
    {
        return \bin2hex(\random_bytes(16));
    }

    /** @throws EndUserException */
    public function sendScheduled(\DateTimeImmutable $datetime): void
    {
        $scheduledFeeds = $this->feeds->getScheduled($datetime);

        foreach ($scheduledFeeds as $scheduledFeed) {
            try {
                $this->deliverFeed($scheduledFeed);
            } catch (\Throwable $feedFailure) {
                // ponytail: one broken feed must not suppress delivery of the
                // other co-scheduled feeds; quarantine and keep going.
                error_log(sprintf('Skipping feed %s: %s', $scheduledFeed->getUri(), $feedFailure->getMessage()));
            }
        }
    }

    /** @throws EndUserException|\Random\RandomException */
    private function deliverFeed(Feed $scheduledFeed): void
    {
        $feed = $this->feeds->retrieveWithPosts($scheduledFeed);

        // Posts arrive newest→oldest. Collect every post newer than the
        // watermark (lastSentPostUri); stop at it, since it and everything
        // after were already sent.
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
            return;
        }

        // Fail safe to newest-only when the delivery state is unknown: first
        // delivery (no watermark yet) and a watermark that vanished from the
        // publisher-controlled document (rolling window overflow or deliberate
        // drop) must not replay the historical backlog as one digest.
        if ($feed->lastSentPostUri === null || ! $watermarkFound) {
            $newPosts = [$newPosts[0]];
        }

        /** @var list<Subscription> $activeSubscriptions */
        $activeSubscriptions = $this->subscriptionsDAO->findActiveSubscriptionsFor($feed);
        $this->newsletter->sendPostsToSubscribers($feed, $newPosts, ...$activeSubscriptions);

        // $newPosts is newest-first; advance the watermark to the newest sent.
        $this->feeds->updateLastSentPost($feed, $newPosts[0]);
    }
}
