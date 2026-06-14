<?php

declare(strict_types=1);

namespace SimpleNewsletter\Models;

use SimpleNewsletter\Components\Auth;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Data\Subscription;
use SimpleNewsletter\Data\SubscriptionsDAO;

final readonly class Subscriptions
{
    public function __construct(
        private SubscriptionsDAO $subscriptionsDAO,
        private Feeds $feeds,
        private Newsletter $newsletter,
        private Auth $auth,
    ) {}

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

    public function cancel(string $feedUri, string $email, #[\SensitiveParameter] string $token): void
    {
        if (! $this->auth->verify($email, $token)) {
            throw new EndUserException('Invalid token. Please check your cancellation link and try again.');
        }

        $subscription = $this->subscriptionsDAO->find($feedUri, $email);
        if (! $subscription instanceof Subscription) {
            throw new EndUserException('Subscription not found');
        }

        $this->subscriptionsDAO->deactivate($subscription);
    }

    public function sendScheduled(\DateTimeImmutable $datetime): void
    {
        $scheduledFeeds = $this->feeds->getScheduled($datetime);

        foreach ($scheduledFeeds as $scheduledFeed) {
            $feed = $this->feeds->retrieveWithPosts($scheduledFeed);

            $posts = $feed->posts;
            foreach ($posts as $post) {
                if ($post->uri === $feed->lastSentPostUri) {
                    break;
                }

                $this->newsletter->sendPostToSubscribers(
                    $feed,
                    $post,
                    ...$this->subscriptionsDAO->findActiveSubscriptionsFor($feed),
                );

                $this->feeds->updateLastSentPost($feed, $post);

                break;
            }
        }
    }
}
