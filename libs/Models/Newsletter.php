<?php

declare(strict_types=1);

namespace SimpleNewsletter\Models;

use SimpleNewsletter\Components\Auth;
use SimpleNewsletter\Components\EmailTemplateFactory;
use SimpleNewsletter\Components\Sender;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\Post;
use SimpleNewsletter\Data\Subscription;

final readonly class Newsletter
{
    public function __construct(
        private Sender $sender,
        private EmailTemplateFactory $emailTemplateFactory,
        private Auth $auth
    )
    {}

    public function sendConfirmation(
        Feed $feed,
        Subscription $subscription
    ): void {
        $this->sender->send(
            $this->emailTemplateFactory->createConfirmation(
                $subscription,
                $feed,
                $this->auth->hash($subscription->email)
            )
        );
    }

    public function sendPostToSubscribers(
        Feed $feed,
        Post $post,
        Subscription ...$subscriptions,
    ): void {
        foreach ($subscriptions as $subscription) {
            $this->sender->send(
                $this->emailTemplateFactory->createNewsletter(
                    $subscription,
                    $feed,
                    $post,
                    $this->auth->hash($subscription->email)
                )
            );
        }
    }
}
