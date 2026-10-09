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
        private Auth $auth,
    ) {}

    public function sendConfirmation(
        Feed $feed,
        Subscription $subscription,
    ): void {
        $this->sender->send($this->emailTemplateFactory->createConfirmation(
            $subscription,
            $feed,
            $this->auth->hash($this->auth->tokenKey('confirm', $feed->getUri(), $subscription->email, $subscription->tokenNonce)),
        ));
    }

    /**
     * @param non-empty-list<Post> $posts
     */
    public function sendPostsToSubscribers(
        Feed $feed,
        array $posts,
        Subscription ...$subscriptions,
    ): void {
        foreach ($subscriptions as $subscription) {
            try {
                $this->sender->send($this->emailTemplateFactory->createNewsletter(
                    $subscription,
                    $feed,
                    $posts,
                    $this->auth->hash($this->auth->tokenKey('cancel', $feed->getUri(), $subscription->email, $subscription->tokenNonce)),
                ));
            } catch (\Throwable $sendFailure) {
                // ponytail: one bad recipient must not skip the rest of the
                // batch; the failed recipient loses this digest (logged), and
                // the watermark advance after the loop avoids duplicate mail
                // to the recipients who already received it.
                error_log(sprintf('Delivery to %s failed: %s', $subscription->email, $sendFailure->getMessage()));
            }
        }
    }
}
