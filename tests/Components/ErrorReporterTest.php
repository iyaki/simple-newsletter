<?php

declare(strict_types=1);

use SimpleNewsletter\Components\ErrorReporter;

covers(SimpleNewsletter\Components\ErrorReporter::class);

test('report writes the message to the server log', function (): void {
    $previousErrorLogIni = \ini_get('error_log');
    $logFile = \tempnam(\sys_get_temp_dir(), 'errrep-');
    \assert(\is_string($logFile));

    try {
        \ini_set('error_log', $logFile);

        ErrorReporter::report('delivery to user@example.com failed');

        expect(\file_get_contents($logFile))->toContain('delivery to user@example.com failed');
    } finally {
        \ini_set('error_log', \is_string($previousErrorLogIni) ? $previousErrorLogIni : '');
        \unlink($logFile);
    }
});

test('report forwards the caught error to Sentry when a DSN is configured', function (): void {
    if (! \function_exists('\Sentry\init')) {
        $this->markTestSkipped('sentry/sentry is not installed');
    }

    $events = [];
    \Sentry\init([
        'dsn' => 'https://public@sentry.example.com/1',
        'before_send' => static function (\Sentry\Event $event) use (&$events): ?\Sentry\Event {
            $events[] = $event;

            return null; // swallow: never leave this test's process
        },
    ]);

    $error = new \RuntimeException('smtp relay exploded');
    ErrorReporter::report('Delivery to user@example.com failed: smtp relay exploded', $error);

    $event = $events[0] ?? null;
    expect($event)->not->toBeNull()
        ->and($event?->getExceptions()[0]?->getValue())->toBe('smtp relay exploded');

    \Sentry\init(['dsn' => null]); // de-init so later tests stay Sentry-free
});

test('report without an error logs a warning-level message event', function (): void {
    if (! \function_exists('\Sentry\init')) {
        $this->markTestSkipped('sentry/sentry is not installed');
    }

    $events = [];
    \Sentry\init([
        'dsn' => 'https://public@sentry.example.com/1',
        'before_send' => static function (\Sentry\Event $event) use (&$events): ?\Sentry\Event {
            $events[] = $event;

            return null;
        },
    ]);

    ErrorReporter::report('send-newsletters: lock file unavailable');

    $event = $events[0] ?? null;
    expect($event)->not->toBeNull()
        ->and($event?->getLevel()?->__toString())->toBe('warning');

    \Sentry\init(['dsn' => null]);
});

test('expected forwards the caught error to Sentry as a warning-level event', function (): void {
    if (! \function_exists('\Sentry\init')) {
        $this->markTestSkipped('sentry/sentry is not installed');
    }

    $previousErrorLogIni = \ini_get('error_log');
    $logFile = \tempnam(\sys_get_temp_dir(), 'errrep-');
    \assert(\is_string($logFile));
    \ini_set('error_log', $logFile);

    $events = [];
    \Sentry\init([
        'dsn' => 'https://public@sentry.example.com/1',
        'before_send' => static function (\Sentry\Event $event) use (&$events): ?\Sentry\Event {
            $events[] = $event;

            return null;
        },
    ]);

    try {
        $error = new \RuntimeException('DOMDocument cannot parse XML');
        ErrorReporter::expected('Feed https://example.com/feed failed to fetch or parse', $error);

        $event = $events[0] ?? null;
        expect($event)->not->toBeNull()
            ->and($event?->getLevel()?->__toString())->toBe('warning')
            ->and($event?->getExceptions()[0]?->getValue())->toBe('DOMDocument cannot parse XML')
            ->and(\file_get_contents($logFile))->toContain('failed to fetch or parse');
    } finally {
        \ini_set('error_log', \is_string($previousErrorLogIni) ? $previousErrorLogIni : '');
        \unlink($logFile);
        \Sentry\init(['dsn' => null]);
    }
});
