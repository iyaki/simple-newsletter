<?php

declare(strict_types=1);

namespace SimpleNewsletter\Templates\Email;

use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\Post;
use SimpleNewsletter\Data\Subscription;

final readonly class Newsletter implements EmailInterface
{
    public function __construct(
        private Subscription $subscription,
        private Feed $feed,
        private Post $post,
        private string $cancellationURI,
    ) {}

    #[\Override]
    public function recipient(): string
    {
        return $this->subscription->email;
    }

    #[\Override]
    public function subject(): string
    {
        return $this->post->title . ' - ' . $this->feed->getTitle();
    }

    #[\Override]
    public function body(): string
    {
        $fontStack = "Rockwell,'Rockwell Nova','Roboto Slab','DejaVu Serif','Sitka Small',serif";
        return <<<HTML
            <a href="{$this->post->uri}">Visit original website</a>
            <div style="max-width:60ch;margin:0 auto;font-size:18px;line-height:1.5;font-family:{$fontStack}">
                {$this->post->content}
            </div>
            <p><a href="{$this->cancellationURI}">To cancel your subscription to this newsletter click here</a></p>
            HTML;
    }
}
