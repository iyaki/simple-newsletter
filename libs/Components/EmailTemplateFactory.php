<?php

declare(strict_types=1);

namespace SimpleNewsletter\Components;

use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\Post;
use SimpleNewsletter\Data\Subscription;
use SimpleNewsletter\Templates\Email\Newsletter;
use SimpleNewsletter\Templates\Email\SubscriptionConfirmation;

final readonly class EmailTemplateFactory
{
    public function __construct(
        private string $serviceHost
    )
    {}

    public function createConfirmation(
        Subscription $subscription,
        Feed $feed,
        string $token,
    ): SubscriptionConfirmation
    {
        $recipient = $subscription->email;

        return new SubscriptionConfirmation(
            $recipient,
            $feed,
            \sprintf(
                '%s/v1/subscriptions/confirmation/?uri=%s&email=%s&token=%s',
                $this->serviceHost,
                \urlencode($feed->uri),
                \urlencode($recipient),
                \urlencode($token)
            )
        );
    }

    public function createNewsletter(
        Subscription $subscription,
        Feed $feed,
        Post $post,
        string $token
    ): Newsletter {
        return new Newsletter(
            $subscription,
            $feed,
            $post,
            \sprintf(
                '%s/v1/subscriptions/cancellation/?uri=%s&email=%s&token=%s',
                $this->serviceHost,
                \urlencode($feed->uri),
                \urlencode($subscription->email),
                \urlencode($token)
            )
        );
    }
}
