<?php

declare(strict_types=1);

namespace SimpleNewsletter\Adapters;

use Laminas\Http\Client\Adapter\Exception\RuntimeException as AdapterRuntimeException;
use Laminas\Http\Client\Adapter\Socket;

/**
 * Socket adapter enforcing a total-duration budget per connection, a
 * response size budget via FeedFetchByteCapFilter, and a public-destination
 * egress policy via PrivateAddressGuard, so a single feed fetch can neither
 * hold a worker indefinitely, exhaust memory, nor reach internal targets
 * (including across redirect hops).
 */
final class BudgetedSocket extends Socket
{
    public const int MAX_BYTES = 10_000_000;

    public const int MAX_SECONDS = 60;

    private const string FILTER_NAME = 'simple-newsletter-feed-byte-cap';

    private static bool $filterRegistered = false;

    private float $deadline = 0.0;

    // The vendor parent declares no parameter types, so this override must not add any.
    // @phpstan-ignore missingType.parameter
    #[\Override]
    public function connect($host, $port = 80, $secure = false): void
    {
        if (\is_string($host) && ! PrivateAddressGuard::privateFeedsAllowed()
            && ! PrivateAddressGuard::hostIsPublic($host)
        ) {
            throw new AdapterRuntimeException('Feed fetch refused: destination is not publicly routable.');
        }

        if (! self::$filterRegistered) {
            \stream_filter_register(self::FILTER_NAME, FeedFetchByteCapFilter::class);
            self::$filterRegistered = true;
        }

        $this->deadline = \microtime(true) + self::MAX_SECONDS;
        parent::connect($host, $port, $secure);

        if (\is_resource($this->socket)) {
            \stream_filter_append($this->socket, self::FILTER_NAME, STREAM_FILTER_READ, ['limit' => self::MAX_BYTES]);
        }
    }

    #[\Override]
    public function read(): string
    {
        if (\microtime(true) > $this->deadline) {
            $this->close();
            throw new AdapterRuntimeException('Feed fetch exceeded the total time budget.');
        }

        return parent::read();
    }
}
