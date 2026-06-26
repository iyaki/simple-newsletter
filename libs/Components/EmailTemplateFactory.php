<?php

declare(strict_types=1);

namespace SimpleNewsletter\Components;

use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\Post;
use SimpleNewsletter\Data\Subscription;
use SimpleNewsletter\Templates\Email\Newsletter;
use SimpleNewsletter\Templates\Email\SubscriptionConfirmation;

/**
 * Factory for creating email templates.
 *
 * Constructs confirmation and newsletter email templates with signed token links
 * for subscription management. Requires service host URI for link generation.
 *
 * @method SubscriptionConfirmation createConfirmation(Subscription, Feed, string)
 * @method Newsletter createNewsletter(Subscription, Feed, Post, string)
 */
final readonly class EmailTemplateFactory
{
    public function __construct(
        private string $serviceHost,
    ) {}

    public function createConfirmation(
        Subscription $subscription,
        Feed $feed,
        #[\SensitiveParameter]
        string $token,
    ): SubscriptionConfirmation {
        $recipient = $subscription->email;

        return new SubscriptionConfirmation(
            $recipient,
            $feed,
            \sprintf(
                '%s/v1/subscriptions/confirmation/?uri=%s&email=%s&token=%s',
                $this->serviceHost,
                \urlencode($feed->getUri()),
                \urlencode($recipient),
                \urlencode($token),
            ),
        );
    }

    public function createNewsletter(
        Subscription $subscription,
        Feed $feed,
        Post $post,
        #[\SensitiveParameter]
        string $token,
    ): Newsletter {
        return new Newsletter(
            $subscription,
            $feed,
            $post,
            \sprintf(
                '%s/v1/subscriptions/cancellation/?uri=%s&email=%s&token=%s',
                $this->serviceHost,
                \urlencode($feed->getUri()),
                \urlencode($subscription->email),
                \urlencode($token),
            ),
        );
    }
}
