<?php

declare(strict_types=1);

namespace SimpleNewsletter\Templates\Email;

use SimpleNewsletter\Data\Feed;
use SimpleNewsletter\Data\Post;
use SimpleNewsletter\Data\Subscription;

final readonly class Newsletter implements EmailInterface
{
    /**
     * @param non-empty-list<Post> $posts Newest-first.
     */
    public function __construct(
        private Subscription $subscription,
        private Feed $feed,
        private array $posts,
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
        $count = \count($this->posts);
        $title = $count > 1
            ? $this->posts[0]->title . ' (+' . ($count - 1) . ' more)'
            : $this->posts[0]->title;

        return $title . ' - ' . $this->feed->getTitle();
    }

    #[\Override]
    public function body(): string
    {
        $fontStack = "Rockwell,'Rockwell Nova','Roboto Slab','DejaVu Serif','Sitka Small',serif";

        $blocks = [];
        foreach ($this->posts as $index => $post) {
            if ($index > 0) {
                $blocks[] = '<hr style="border:none;border-top:1px solid #ccc;margin:2em 0">';
            }
            $blocks[] = '<article>';
            $blocks[] = '<h2 style="margin:0 0 .5em;font-size:1.3em"><a href="' . $post->uri . '">' . $post->title . '</a></h2>';
            $blocks[] = $post->content;
            $blocks[] = '</article>';
        }

        $postsHtml = \implode("\n", $blocks);

        return <<<HTML
            <div style="max-width:60ch;margin:0 auto;font-size:18px;line-height:1.5;font-family:{$fontStack}">
                {$postsHtml}
            </div>
            <p><a href="{$this->cancellationURI}">To cancel your subscription to this newsletter click here</a></p>
            HTML;
    }
}
