<?php

declare(strict_types=1);

namespace SimpleNewsletter\Templates\Email;

use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\Post;

final readonly class Newsletter implements EmailInterface
{
    /**
     * @param string[] $recipients
     */
    public function __construct(
        private array $recipients,
        private Feed $feed,
        private Post $post
    )
    {}

    /**
     * @return string[]
     */
    public function recipients(): array
    {
        return $this->recipients;
    }

    public function subject(): string
    {
        return $this->post->title . ' - ' . $this->feed->title;
    }

    public function body(): string
    {
        $fontStack = "Rockwell,'Rockwell Nova','Roboto Slab','DejaVu Serif','Sitka Small',serif";
        return <<<HTML
        <a href="{$this->post->uri}">Visit original website</a>
        <div style="max-width:60ch;margin:0 auto;font-size:18px;line-height:1.5;font-family:{$fontStack}">
            {$this->post->content}
        </div>
        HTML;
    }
}
