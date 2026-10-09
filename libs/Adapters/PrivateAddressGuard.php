<?php

declare(strict_types=1);

namespace SimpleNewsletter\Adapters;

use SimpleNewsletter\Components\EndUserException;

/**
 * Destination policy for user-supplied feed URIs: only publicly routable
 * hosts may be fetched server-side. Blocks loopback, link-local, RFC1918
 * and reserved ranges (including cloud metadata endpoints) both for the
 * initial URI and for every redirect hop via EgressCheckedSocket.
 */
final class PrivateAddressGuard
{
    public const string ALLOW_ENV = 'NEWSLETTER_ALLOW_PRIVATE_FEEDS';

    public static function privateFeedsAllowed(): bool
    {
        // ponytail: single env kill-switch for test/dev feed fixtures on
        // loopback; documented in example.env, must stay off in production.
        return \getenv(self::ALLOW_ENV) === '1';
    }

    /** @throws EndUserException when the URI host is not publicly routable */
    public static function assertUriHostIsPublic(string $uri): void
    {
        if (self::privateFeedsAllowed()) {
            return;
        }

        $host = \parse_url($uri, \PHP_URL_HOST);
        if (! \is_string($host) || $host === '' || ! self::hostIsPublic($host)) {
            throw new EndUserException('Invalid Feed URI');
        }
    }

    public static function hostIsPublic(string $host): bool
    {
        if (\filter_var($host, \FILTER_VALIDATE_IP) !== false) {
            return self::ipIsPublic($host);
        }

        // gethostbyname returns the unmodified hostname when resolution fails.
        $ip = \gethostbyname($host);
        if (\filter_var($ip, \FILTER_VALIDATE_IP) === false) {
            // ponytail: A records only; add AAAA handling if a real feed ever needs it.
            return false;
        }

        return self::ipIsPublic($ip);
    }

    private static function ipIsPublic(string $ip): bool
    {
        return \filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
