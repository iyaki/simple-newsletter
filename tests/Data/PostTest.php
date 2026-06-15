<?php

declare(strict_types=1);

use SimpleNewsletter\Data\Post;

test('Post DTO constructor sets properties', function (): void {
    $post = new Post('https://example.com/post', 'Test Title', '<p>Hello</p>');
    expect($post->uri)->toBe('https://example.com/post');
    expect($post->title)->toBe('Test Title');
    expect($post->content)->toBe('<p>Hello</p>');
});
