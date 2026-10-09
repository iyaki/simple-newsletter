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

    private const int RESEND_INTERVAL_SECONDS = 3600;

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

        $subscription = $this->subscriptionsDAO->find($feedUri, $email)
            ?? $this->createPendingSubscription($feedUri, $email);

        if ($subscription->active) {
            throw new EndUserException('You are already subscribed to this feed.');
        }

        // ponytail: a pending row may not trigger unlimited confirmation
        // mail (email bombing); one confirmation per interval is enough.
        if (\time() - $subscription->confirmationSentAt < self::RESEND_INTERVAL_SECONDS) {
            return;
        }

        $this->newsletter->sendConfirmation($feed, $subscription);
        $this->subscriptionsDAO->markConfirmationSent($subscription);
    }

    /** @throws EndUserException|RandomException */
    private function createPendingSubscription(string $feedUri, string $email): Subscription
    {
        $subscription = new Subscription($feedUri, $email, tokenNonce: $this->auth->newNonce());
        $this->subscriptionsDAO->new($subscription);

        return $subscription;
    }

    /** @throws EndUserException */
    public function confirm(string $feedUri, string $email, #[\SensitiveParameter] string $token): void
    {
        $subscription = $this->subscriptionsDAO->find($feedUri, $email);

        if (! $subscription instanceof Subscription) {
            throw new EndUserException('Subscription not found. The link may be invalid or expired.');
        }

        $key = $this->auth->tokenKey('confirm', $feedUri, $email, $subscription->tokenNonce);
        if (! $this->auth->verify($key, $token)) {
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

        $key = $this->auth->tokenKey('cancel', $feedUri, $email, $subscription->tokenNonce);
        if (! $this->auth->verify($key, $token)) {
            throw new EndUserException('Invalid token. Please check your cancellation link and try again.');
        }

        $this->subscriptionsDAO->delete($subscription);
    }
}
