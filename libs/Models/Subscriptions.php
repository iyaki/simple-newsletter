<?php

declare(strict_types=1);

namespace SimpleNewsletter\Models;

use SimpleNewsletter\Components\Auth;
use SimpleNewsletter\Components\EndUserException;
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
        if (! \filter_var($feedUri, \FILTER_VALIDATE_URL)) {
            throw new EndUserException('Invalid Feed URI');
        }

        if (!\filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new EndUserException('Invalid email address');
        }

        $feed = $this->feeds->retrieve($feedUri);
        $token = $this->auth->hash($email);

        $subject = 'Newsletter subscription confirmation';
        $message = <<<HTML
            <h1>Thank you for subscribing to <a href="{$feed->link}" target="_blank">{$feed->title}</a>.</h1>
            <p>Please confirm your subscription by clicking the following link:</p>
            <p><a href="{$this->serviceHost}/subscriptions/confirm?feedUri={$uri}&email={$email}&token={$token}">Confirm Subscription</a>. Or copy and paste the following link into your browser: {$this->serviceHost}/subscriptions/confirm?feedUri={$uri}&email={$email}&token={$token}</p>
            <p>If you did not request this subscription, please ignore this email.</p>
            <p>Thank you.</p>
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
