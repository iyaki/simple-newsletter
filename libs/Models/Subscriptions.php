<?php

declare(strict_types=1);

namespace SimpleNewsletter\Models;

use SimpleNewsletter\Components\Auth;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Data\Database;
use SimpleNewsletter\Data\FeedsDAO;
use SimpleNewsletter\Data\Subscription;
use SimpleNewsletter\Data\SubscriptionsDAO;

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


        $encodedToken = \urlencode($this->auth->hash($email));
        $encodedUri = \urlencode($feedUri);
        $encodedEmail = \urlencode($email);

        $subject = 'Newsletter subscription confirmation';
        $message = <<<HTML
            <h1>Thank you for subscribing to <a href="{$feed->link}" target="_blank">{$feed->title}</a>.</h1>
            <p>Please confirm your subscription by clicking the following link: <a href="{$this->serviceHost}/subscriptions/confirmation/?uri={$encodedUri}&email={$encodedEmail}&token={$encodedToken}">Confirm Subscription</a>.</p>
            <p>Or copy and paste the following link into your browser: <code>{$this->serviceHost}/subscriptions/confirmation/?uri={$encodedUri}&email={$encodedEmail}&token={$encodedToken}</code></p>
            <p>If you did not request this subscription, please ignore this email.</p>
            <p>Thank you.</p>
        HTML;

        $this->sender->send([$email], $subject, $message);
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

    public function sendScheduled(): void
    {

    }
}
