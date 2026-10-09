<?php

declare(strict_types=1);

namespace SimpleNewsletter\Templates\Email;

use SimpleNewsletter\Data\Feed;

final readonly class SubscriptionConfirmation implements EmailInterface
{
    public function __construct(
        private string $recipient,
        private Feed $feed,
        private string $confirmationURI,
    ) {}

    #[\Override]
    public function recipient(): string
    {
        return $this->recipient;
    }

    #[\Override]
    public function subject(): string
    {
        return 'Newsletter subscription confirmation';
    }

    #[\Override]
    public function body(): string
    {
        $esc = static fn (string $value): string => \htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, encoding: 'UTF-8');

        return <<<HTML
                <h1>Thank you for subscribing to <a href="{$esc($this->feed->getLink())}" target="_blank">{$esc($this->feed->getTitle())}</a>.</h1>
                <p>Please confirm your subscription by clicking the following link: <a href="{$this->confirmationURI}">Confirm Subscription</a>.</p>
                <p>Or copy and paste the following link into your browser: <code>{$this->confirmationURI}</code></p>
                <p>If you did not request this subscription, please ignore this email.</p>
                <p>Thank you.</p>
            HTML;
    }
}
