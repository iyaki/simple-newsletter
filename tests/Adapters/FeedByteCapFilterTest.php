<?php

declare(strict_types=1);

use SimpleNewsletter\Adapters\FeedFetchByteCapFilter;

test('feed byte cap filter passes data below the limit unchanged', function (): void {
    \stream_filter_register('byte-cap-under', FeedFetchByteCapFilter::class);
    $stream = \fopen('php://temp', mode: 'w+b');
    \assert(\is_resource($stream));
    \fwrite($stream, 'hello feeds');
    \rewind($stream);
    \stream_filter_append($stream, 'byte-cap-under', STREAM_FILTER_READ, ['limit' => 1024]);

    $read = (string) \fread($stream, 1024);

    expect($read)->toBe('hello feeds');
});

test('feed byte cap filter truncates data beyond the limit', function (): void {
    \stream_filter_register('byte-cap-over', FeedFetchByteCapFilter::class);
    $stream = \fopen('php://temp', mode: 'w+b');
    \assert(\is_resource($stream));
    \fwrite($stream, \str_repeat('A', 100));
    \rewind($stream);
    \stream_filter_append($stream, 'byte-cap-over', STREAM_FILTER_READ, ['limit' => 10]);

    $read = (string) \fread($stream, 1024);

    expect($read)->toBe(\str_repeat('A', 10));
});
