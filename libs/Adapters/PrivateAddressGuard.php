<?php

declare(strict_types=1);

namespace SimpleNewsletter\Adapters;

use SimpleNewsletter\Components\EndUserException;

/**
 * Destination policy for user-supplied feed URIs: only publicly routable
 * hosts may be fetched server-side. Blocks loopback, link-local, RFC1918
 * and reserved ranges (including cloud metadata endpoints) both for the
 * initial URI and for every redirect hop via BudgetedSocket.
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
        return self::pinnedDialTarget($host) !== null;
    }

    /**
     * Validated dial target for BudgetedSocket: the first resolved address
     * (bracketed for IPv6), or null when the host is a private/reserved
     * literal or ANY resolved address is non-public. Null also covers
     * unresolvable hosts.
     */
    public static function pinnedDialTarget(string $host): ?string
    {
        // parse_url keeps brackets on IPv6 literals; strip them before checks.
        $host = \trim($host, characters: '[]');

        if (\filter_var($host, \FILTER_VALIDATE_IP) !== false) {
            return self::ipIsPublic($host) ? $host : null;
        }

        $addresses = self::resolveAddresses($host);
        if ($addresses === []) {
            return null;
        }

        foreach ($addresses as $ip) {
            if (! self::ipIsPublic($ip)) {
                return null;
            }
        }

        $first = $addresses[0];

        return \str_contains($first, ':') ? "[{$first}]" : $first;
    }

    /** @return list<string> all A/AAAA addresses for the host, empty when unresolvable */
    public static function resolveAddresses(string $host): array
    {
        $records = \dns_get_record($host, \DNS_A | \DNS_AAAA);
        if (! \is_array($records)) {
            return [];
        }

        $addresses = [];
        foreach ($records as $record) {
            if (! \is_array($record)) {
                continue;
            }

            if (($record['type'] ?? null) === 'A') {
                $ip = $record['ip'] ?? null;
                if (\is_string($ip)) {
                    $addresses[] = $ip;
                }
            } elseif (($record['type'] ?? null) === 'AAAA') {
                $ipv6 = $record['ipv6'] ?? null;
                if (\is_string($ipv6)) {
                    $addresses[] = $ipv6;
                }
            }
        }

        return $addresses;
    }

    public static function ipIsPublic(string $ip): bool
    {
        return \filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
