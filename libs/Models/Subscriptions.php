<?php

declare(strict_types=1);

namespace SimpleNewsletter\Models;

use SimpleNewsletter\Components\Auth;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Components\Sender;
use SimpleNewsletter\Data\Database;
use SimpleNewsletter\Data\FeedsDAO;
use SimpleNewsletter\Data\Subscription;
use SimpleNewsletter\Data\SubscriptionsDAO;
use SimpleNewsletter\Templates\Email\SubscriptionConfirmation;

final readonly class Subscriptions
{
    public function __construct(
        private SubscriptionsDAO $subscriptionsDAO,
        private Feeds $feeds,
        private Newsletter $newsletter,
        private Auth $auth
    )
    {}

    public function add(string $feedUri, string $email): void
    {
        if (! \filter_var($feedUri, \FILTER_VALIDATE_URL)) {
            throw new EndUserException('Invalid Feed URI');
        }

        $feed = $this->feeds->retrieve($feedUri);

        if (!\filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new EndUserException('Invalid email address');
        }

        $subscription = $this->subscriptionsDAO->find($feedUri, $email);

        if ($subscription instanceof Subscription) {
            if ($subscription->active) {
                throw new EndUserException('Already subscribed');
            }
        } else {
            $subscription = new Subscription($feedUri, $email);
            $this->subscriptionsDAO->new($subscription);
        }

        $this->newsletter->sendConfirmation(
            $feed,
            $subscription
        );
    }

    public function confirm(string $feedUri, string $email, string $token): void
    {
        if (!$this->auth->verify($email, $token)) {
            throw new EndUserException('Invalid token');
        }

        $subscription = $this->subscriptionsDAO->find($feedUri, $email);

        if (! $subscription instanceof Subscription) {
            throw new EndUserException('Invalid subscripion');
        }

        $this->subscriptionsDAO->activate($subscription);
    }

    public function cancel(string $feedUri, string $email, string $token): void
    {
        if (!$this->auth->verify($email, $token)) {
            throw new EndUserException('Invalid token');
        }

        $subscription = $this->subscriptionsDAO->find($feedUri, $email);
        $this->subscriptionsDAO->deactivate($subscription);
    }

    public function sendScheduled(\DateTimeImmutable $datetime): void
    {
        $scheduledFeeds = $this->feeds->getSchedudled($datetime);

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
