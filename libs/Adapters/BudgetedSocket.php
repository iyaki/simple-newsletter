<?php

declare(strict_types=1);

namespace SimpleNewsletter\Adapters;

use Laminas\Http\Client\Adapter\Exception\RuntimeException as AdapterRuntimeException;
use Laminas\Http\Client\Adapter\Socket;
use Laminas\Stdlib\ErrorHandler;

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
    /**
     * @throws AdapterRuntimeException when the destination is refused or the pinned connection fails
     * @throws \ErrorException for underlying PHP stream warnings (mirrors vendor Socket error handling)
     */
    #[\Override]
    public function connect($host, $port = 80, $secure = false): void
    {
        if (PrivateAddressGuard::privateFeedsAllowed()) {
            // Escape hatch: dial the host as given (loopback test fixtures).
            parent::connect($host, (int) $port, (bool) $secure);
        } else {
            $pinned = PrivateAddressGuard::pinnedDialTarget((string) $host);
            if ($pinned === null) {
                throw new AdapterRuntimeException('Feed fetch refused: destination is not publicly routable.');
            }

            $this->connectPinned((string) $host, $pinned, (int) $port, (bool) $secure);
        }

        $this->deadline = \microtime(true) + self::MAX_SECONDS;
        $this->appendByteCapFilter();
    }

    /**
     * Dial the validated address instead of the hostname so a DNS rebinding
     * between validation and connect cannot reroute the fetch; ssl.peer_name
     * keeps certificate/SNI validation on the original hostname.
     *
     * ponytail: no keepalive/persistent-socket support in the pinned path
     * (the app does not configure them); otherwise mirrors vendor
     * Socket::connect semantics.
     *
     * @throws AdapterRuntimeException when the connection, TLS handshake, or timeout setup fails
     * @throws \ErrorException for underlying PHP stream warnings
     */
    private function connectPinned(string $host, string $pinned, int $port, bool $secure): void
    {
        $unbracketed = \trim($host, characters: '[]');
        $sslOptions = [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ];
        if (\filter_var($unbracketed, \FILTER_VALIDATE_IP) === false) {
            $sslOptions['peer_name'] = $unbracketed;
        }
        $context = \stream_context_create(['ssl' => $sslOptions]);

        $connectTimeout = (int) ($this->config['connecttimeout'] ?? $this->config['timeout'] ?? 10);

        ErrorHandler::start();
        $socket = \stream_socket_client('tcp://' . $pinned . ':' . $port, timeout: $connectTimeout, flags: STREAM_CLIENT_CONNECT, context: $context);
        $error = ErrorHandler::stop();

        if ($socket === false) {
            throw new AdapterRuntimeException(
                \sprintf(
                    'Unable to connect to %s:%d%s',
                    $host,
                    $port,
                    $error !== null ? ' . Error #' . $error->getCode() . ': ' . $error->getMessage() : '',
                ),
                0,
                $error,
            );
        }
        $this->socket = $socket;

        if (! \stream_set_timeout($this->socket, (int) ($this->config['timeout'] ?? 10))) {
            $this->close();
            throw new AdapterRuntimeException('Unable to set the connection timeout');
        }

        $sslTransport = (string) ($this->config['ssltransport'] ?? 'tls');

        if ($secure) {
            try {
                if ($this->setSslCryptoMethod) {
                    $this->enableCryptoTransport($sslTransport, $this->socket, $host);
                } elseif (! \stream_socket_enable_crypto($this->socket, enable: true, crypto_method: STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new AdapterRuntimeException('Unable to enable TLS on the connection');
                }
            } catch (AdapterRuntimeException $e) {
                $this->close();
                throw $e;
            }

            $this->connectedTo = [$sslTransport . '://' . $host, $port];
        } else {
            $this->connectedTo = ['tcp://' . $host, $port];
        }
    }

    private function appendByteCapFilter(): void
    {
        if (! self::$filterRegistered) {
            \stream_filter_register(self::FILTER_NAME, FeedFetchByteCapFilter::class);
            self::$filterRegistered = true;
        }

        if (\is_resource($this->socket)) {
            \stream_filter_append($this->socket, self::FILTER_NAME, STREAM_FILTER_READ, ['limit' => self::MAX_BYTES, 'deadline' => $this->deadline]);
        }
    }

    #[\Override]
    public function read(): string
    {
        if (\microtime(true) > $this->deadline) {
            $this->close();
            throw new AdapterRuntimeException('Feed fetch exceeded the total time budget.');
        }

        // The deadline is not only checked here (laminas calls read() once per
        // request); the stream filter enforces it on every bucket so a
        // trickle origin cannot hold the single parent::read() loop open.
        return parent::read();
    }
}
