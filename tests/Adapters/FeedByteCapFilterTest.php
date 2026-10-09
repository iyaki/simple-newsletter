<?php

declare(strict_types=1);

namespace Tests\Adapters;

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

test('feed byte cap filter keeps swallowing data after the cap was hit', function (): void {
    \stream_filter_register('byte-cap-swallow', FeedFetchByteCapFilter::class);
    $stream = \fopen('php://temp', mode: 'w+b');
    \assert(\is_resource($stream));
    \fwrite($stream, \str_repeat('A', 100));
    \rewind($stream);
    \stream_filter_append($stream, 'byte-cap-swallow', STREAM_FILTER_READ, ['limit' => 10]);

    $first = (string) \fread($stream, 1024);
    \fwrite($stream, \str_repeat('B', 100));
    $second = (string) \fread($stream, 1024);

    expect($first)->toBe(\str_repeat('A', 10))
        ->and($second)->toBe('');
});

test('feed byte cap filter enforces the total-duration deadline mid-stream', function (): void {
    \stream_filter_register('byte-cap-deadline-past', FeedFetchByteCapFilter::class);
    \stream_filter_register('byte-cap-deadline-future', FeedFetchByteCapFilter::class);

    $expired = \fopen('php://temp', mode: 'w+b');
    \assert(\is_resource($expired));
    \fwrite($expired, \str_repeat('A', 50));
    \rewind($expired);
    \stream_filter_append($expired, 'byte-cap-deadline-past', STREAM_FILTER_READ, ['limit' => 1024, 'deadline' => \microtime(true) - 1.0]);

    $live = \fopen('php://temp', mode: 'w+b');
    \assert(\is_resource($live));
    \fwrite($live, \str_repeat('B', 50));
    \rewind($live);
    \stream_filter_append($live, 'byte-cap-deadline-future', STREAM_FILTER_READ, ['limit' => 1024, 'deadline' => \microtime(true) + 60.0]);

    // Past deadline: no data passes, so the blocked read trips the socket
    // idle timeout instead of waiting out a trickle origin.
    expect((string) \fread($expired, 1024))->toBe('')
        // Live deadline: data still flows.
        ->and((string) \fread($live, 1024))->toBe(\str_repeat('B', 50));
});
