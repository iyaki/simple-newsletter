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
use SimpleNewsletter\Templates\Email\Newsletter;
use SimpleNewsletter\Templates\Email\SubscriptionConfirmation;

final readonly class Subscriptions
{
    public function __construct(
        private SubscriptionsDAO $subscriptionsDAO,
        private Feeds $feeds,
        private Auth $auth,
        private Sender $sender,
        private string $serviceHost
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

        $confirmationTemplate = new SubscriptionConfirmation(
            [$email],
            \sprintf(
                '%s/subscriptions/confirmation/?uri=%s&email=%s&token=%s',
                $this->serviceHost,
                \urlencode($feedUri),
                \urlencode($email),
                \urlencode($this->auth->hash($email))
            ),
            $feed
        );

        $this->sender->send($confirmationTemplate);
    }

    public function confirm(string $feedUri, string $email, string $token): void
    {
        if (!$this->auth->verify($email, $token)) {
            throw new EndUserException('Invalid token');
        }

        $subscription = $this->subscriptionsDAO->find($feedUri, $email);
        $this->subscriptionsDAO->activate($subscription);
    }

    public function remove(string $feedUri, string $email, string $token): void
    {

    }

    public function sendScheduled(\DateTimeImmutable $datetime): void
    {
        $scheduledFeeds = $this->feeds->getSchedudled($datetime);

        foreach ($scheduledFeeds as $scheduledFeed) {
            $limit = 3;

            if (! $scheduledFeed->lastSentPostUri) {
                $limit = 1;
            }

            $recipientsEmail = null;

            $feed = $this->feeds->retrieveWithPosts($scheduledFeed);
            $posts = $feed->posts;
            $c = 0;
            $lastSentPost = null;
            foreach ($posts as $post) {
                if ($post->uri === $feed->lastSentPostUri) {
                    break;
                }

                if ($recipientsEmail === null) {
                    $recipientsEmail = \array_map(
                        fn (Subscription $subscription): string => $subscription->email,
                        $this->subscriptionsDAO->findActiveSubscriptionsFor($feed)
                    );
                }

                $template = new Newsletter(
                    $recipientsEmail,
                    $feed,
                    $post
                );

                $this->sender->send($template);

                $this->feeds->updateLastSentPost($feed, $post);

                break;
            }
        }
    }
}
