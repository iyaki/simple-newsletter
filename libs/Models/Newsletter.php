<?php

declare(strict_types=1);

namespace SimpleNewsletter\Models;

use SimpleNewsletter\Adapters\SenderPHPMailer;
use SimpleNewsletter\Components\Auth;
use SimpleNewsletter\Components\EmailTemplateFactory;
use SimpleNewsletter\Components\EndUserException;
use SimpleNewsletter\Components\ErrorReporter;
use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\Post;
use SimpleNewsletter\Data\Subscription;

final readonly class Newsletter
{
    public function __construct(
        private SenderPHPMailer $sender,
        private EmailTemplateFactory $emailTemplateFactory,
        private Auth $auth,
    ) {}

    /**
     * @throws EndUserException when the confirmation email cannot be sent
     */
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
                $this->sender->send($this->emailTemplateFactory->createNewsletter(
                    $subscription,
                    $feed,
                    $posts,
                    $this->auth->hash($this->auth->tokenKey('cancel', $feed->getUri(), $subscription->email, $subscription->tokenNonce)),
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
