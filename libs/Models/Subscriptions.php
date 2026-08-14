<?php

declare(strict_types=1);

namespace SimpleNewsletter\Models;

use Random\RandomException;
use SimpleNewsletter\Components\Auth;
use SimpleNewsletter\Components\EndUserException;
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
            $subscription = new Subscription($feedUri, $email);
            $this->subscriptionsDAO->new($subscription);
        }

        $this->newsletter->sendConfirmation($feed, $subscription);
    }

    /** @throws EndUserException */
    public function confirm(string $feedUri, string $email, #[\SensitiveParameter] string $token): void
    {
        if (! $this->auth->verify($email, $token)) {
            throw new EndUserException('Invalid token. Please check your confirmation link and try again.');
        }

        $subscription = $this->subscriptionsDAO->find($feedUri, $email);

        if (! $subscription instanceof Subscription) {
            throw new EndUserException('Subscription not found. The link may be invalid or expired.');
        }

        $this->subscriptionsDAO->activate($subscription);
    }

    /** @throws EndUserException */
    public function cancel(string $feedUri, string $email, #[\SensitiveParameter] string $token): void
    {
        if (! $this->auth->verify($email, $token)) {
            throw new EndUserException('Invalid token. Please check your cancellation link and try again.');
        }

        $subscription = $this->subscriptionsDAO->find($feedUri, $email);
        if (! $subscription instanceof Subscription) {
            throw new EndUserException('Subscription not found');
        }

        $this->subscriptionsDAO->delete($subscription);
    }

    /** @throws EndUserException */
    public function sendScheduled(\DateTimeImmutable $datetime): void
    {
        $scheduledFeeds = $this->feeds->getScheduled($datetime);

        foreach ($scheduledFeeds as $scheduledFeed) {
            $feed = $this->feeds->retrieveWithPosts($scheduledFeed);

            // Posts arrive newest→oldest. Collect every post newer than the
            // watermark (lastSentPostUri); stop at it, since it and everything
            // after were already sent.
            /** @var list<\SimpleNewsletter\Data\Post> $newPosts */
            $newPosts = [];
            foreach ($feed->posts as $post) {
                if ($post->uri === $feed->lastSentPostUri) {
                    break;
                }
                $newPosts[] = $post;
            }

            if ($newPosts === []) {
                continue;
            }

            // First delivery (no watermark): seed with only the newest post
            // instead of mailing the entire historical backlog.
            if ($feed->lastSentPostUri === null) {
                $newPosts = [$newPosts[0]];
            }

            /** @var list<Subscription> $activeSubscriptions */
            $activeSubscriptions = $this->subscriptionsDAO->findActiveSubscriptionsFor($feed);
            $this->newsletter->sendPostsToSubscribers($feed, $newPosts, ...$activeSubscriptions);

            // $newPosts is newest-first; advance the watermark to the newest sent.
            $this->feeds->updateLastSentPost($feed, $newPosts[0]);
        }
    }
}
