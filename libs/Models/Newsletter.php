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

    /**
     * The MAC input binds the action, the feed and the per-subscription nonce,
     * mirroring Subscriptions::tokenKey so links authorize exactly one action
     * on one subscription.
     */
    private function tokenKey(string $action, Feed $feed, Subscription $subscription): string
    {
        return $action . '|' . $feed->getUri() . '|' . $subscription->email . '|' . $subscription->tokenNonce;
    }

    public function sendConfirmation(
        Feed $feed,
        Subscription $subscription,
    ): void {
        $this->sender->send($this->emailTemplateFactory->createConfirmation(
            $subscription,
            $feed,
            $this->auth->hash($this->tokenKey('confirm', $feed, $subscription)),
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
                    $this->auth->hash($this->tokenKey('cancel', $feed, $subscription)),
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
