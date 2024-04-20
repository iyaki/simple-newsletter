<?php

declare(strict_types=1);

namespace SimpleNewsletter\Models;

use SimpleNewsletter\Components\Auth;
use SimpleNewsletter\Data\Database;
use SimpleNewsletter\Data\FeedsDAO;

final readonly class Subscriptions
{
    public function __construct(
        private Feeds $feeds,
        private Auth $auth,
        private Sender $sender,
        private string $serviceHost
    )
    {}

    public function add(string $feedUri, string $email): void
    {
        $feed = $this->feeds->retrieve($feedUri);
        $token = $this->auth->hash($email);

        echo '<pre>'; \var_dump($this->serviceHost); exit;

        $subject = 'Subscription Confirmation - Simple Newsletter';
        $message = <<<HTML
            <h1>Thank you for subscribing to {$feed->name}.</h1>
            <p>Please confirm your subscription by clicking the following link:</p>
            <p><a href="{$feed->url}/confirm?feedUri={$feedUri}&email={$email}&token={$token}">Confirm Subscription</a></p>
            <p>If you did not request this subscription, please ignore this email.</p>
            <p>Thank you.</p>
            <p>The Simple Newsletter Team</p>
            <p><a href="{$feed->url}">{$feed->url}</a></p>
            <p><a href="{$feed->url}/unsubscribe?feedUri={$feedUri}&email={$email}&token={$token}">Unsubscribe</a></p>
            <p><a href="{$feed->url}/edit?feedUri={$feedUri}&email={$email}&token={$token}">Edit Subscription</a></p>
            <p><a href="{$feed->url}/delete?feedUri={$feedUri}&email={$email}&token={$token}">Delete Subscription</a></p>
            <p><a href="{$feed->url}/confirm?feedUri={$feedUri}&email={$email}&token={$token}">Confirm Subscription</a></p>
            <p><a href="{$feed->url}/unsubscribe?feedUri={$feedUri}&email={$email}&token={$token}">Unsubscribe</a></p>
            <p><a href="{$feed->url}/edit?feedUri={$feedUri}&email={$email}&token={$token}">Edit Subscription</a></p>
            <p><a href="{$feed->url}/delete?feedUri={$feedUri}&email={$email}&token={$token}">Delete Subscription</a></p>
            <p><a href="{$feed->url}/confirm?feedUri={$feedUri}&email={$email}&token={$token}">Confirm Subscription</a></p>
            <p><a href="{$feed->url}/unsubscribe?feedUri={$feedUri}&email={$email}&token={$token}">Unsubscribe</a></p>
            <p><a href="{$feed->url}/edit?feedUri={$feedUri}&email={$email}&token={$token}">Edit Subscription</a></p>
            <p><a href="{$feed->url}/delete?feedUri={$feedUri}&email={$email}&token={$token}">Delete Subscription</a></p>
        HTML;

        $this->sender->send([$email], $subject, $message);
    }

    public function confirm(string $feedUri, string $email, string $token): void
    {

    }

    public function remove(string $feedUri, string $email, string $token): void
    {

    }

    public function sendScheduled(): void
    {

    }
}
