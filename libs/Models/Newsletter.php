<?php

declare(strict_types=1);

namespace SimpleNewsletter\Models;

use SimpleNewsletter\Adapters\SenderPHPMailer;
use SimpleNewsletter\Components\Auth;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Components\ErrorReporter;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\Post;
use SimpleNewsletter\Data\Subscription;
use SimpleNewsletter\Templates\Email\Newsletter as NewsletterTemplate;
use SimpleNewsletter\Templates\Email\SubscriptionConfirmation;

final readonly class Newsletter
{
    public function __construct(
        private SenderPHPMailer $sender,
        private string $uriSelf,
        private Auth $auth,
    ) {}

    /**
     * @throws EndUserException when the confirmation email cannot be sent
     */
    public function sendConfirmation(
        Feed $feed,
        Subscription $subscription,
    ): void {
        $token = $this->auth->hash($this->auth->tokenKey('confirm', $feed->getUri(), $subscription->email, $subscription->tokenNonce));
        $this->sender->send(new SubscriptionConfirmation(
            $subscription->email,
            $feed,
            \sprintf(
                '%s/v1/subscriptions/confirmation/?uri=%s&email=%s&token=%s',
                $this->uriSelf,
                \urlencode($feed->getUri()),
                \urlencode($subscription->email),
                \urlencode($token),
            ),
        ));
    }

    /**
     * @throws EndUserException when delivery fails for every recipient
     * @param non-empty-list<Post> $posts
     */
    public function sendPostsToSubscribers(
        Feed $feed,
        array $posts,
        Subscription ...$subscriptions,
    ): void {
        $delivered = 0;
        $lastFailure = null;

        foreach ($subscriptions as $subscription) {
            try {
                $token = $this->auth->hash($this->auth->tokenKey('cancel', $feed->getUri(), $subscription->email, $subscription->tokenNonce));
                $this->sender->send(new NewsletterTemplate(
                    $subscription,
                    $feed,
                    $posts,
                    \sprintf(
                        '%s/v1/subscriptions/cancellation/?uri=%s&email=%s&token=%s',
                        $this->uriSelf,
                        \urlencode($feed->getUri()),
                        \urlencode($subscription->email),
                        \urlencode($token),
                    ),
                ));
                $delivered++;
            } catch (\Throwable $sendFailure) {
                // ponytail: one bad recipient must not skip the rest of the
                // batch; the failed recipient loses this digest (logged +
                // reported), and the watermark advance after the loop avoids
                // duplicate mail to the recipients who already received it.
                $lastFailure = $sendFailure;
                ErrorReporter::report(
                    \sprintf('Delivery to subscriber of %s failed: %s', $feed->getUri(), $sendFailure->getMessage()),
                    $sendFailure,
                );
            }
        }

        // Whole-batch failure must not silently drop the digest: rethrow so
        // the caller quarantines this feed, skips the watermark advance, and
        // the feed is retried at its next scheduled slot.
        if ($delivered === 0 && $subscriptions !== [] && $lastFailure !== null) {
            throw new EndUserException(
                'Newsletter delivery failed for every recipient: ' . $lastFailure->getMessage(),
                0,
                $lastFailure,
            );
        }
    }
}
