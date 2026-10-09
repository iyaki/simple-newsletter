<?php

declare(strict_types=1);

namespace Tests\Adapters;

use SimpleNewsletter\Adapters\FeedImporterLaminas;
use SimpleNewsletter\Adapters\PrivateAddressGuard;
use SimpleNewsletter\Components\EndUserException;

test('private address guard rejects loopback, link-local and RFC1918 hosts', function (): void {
    expect(PrivateAddressGuard::hostIsPublic('127.0.0.1'))->toBeFalse();
    expect(PrivateAddressGuard::hostIsPublic('169.254.169.254'))->toBeFalse();
    expect(PrivateAddressGuard::hostIsPublic('10.0.0.1'))->toBeFalse();
    expect(PrivateAddressGuard::hostIsPublic('192.168.1.1'))->toBeFalse();
    expect(PrivateAddressGuard::hostIsPublic('172.16.0.9'))->toBeFalse();
    expect(PrivateAddressGuard::hostIsPublic('::1'))->toBeFalse();
    expect(PrivateAddressGuard::hostIsPublic('0.0.0.0'))->toBeFalse();
});

test('private address guard accepts public literal IPs and rejects unresolvable hosts', function (): void {
    expect(PrivateAddressGuard::hostIsPublic('1.1.1.1'))->toBeTrue();
    expect(PrivateAddressGuard::hostIsPublic('8.8.8.8'))->toBeTrue();
    expect(PrivateAddressGuard::hostIsPublic('no-such-host.invalid'))->toBeFalse();
});

test('feed import refuses private destinations before any network activity', function (): void {
    $prev = \getenv(PrivateAddressGuard::ALLOW_ENV);
    \putenv(PrivateAddressGuard::ALLOW_ENV);

    try {
        $importer = new FeedImporterLaminas();
        expect(fn () => $importer->fetchNew('http://127.0.0.1:9/loopback.xml'))
            ->toThrow(EndUserException::class, 'Invalid Feed URI');
        expect(fn () => $importer->fetchNew('http://169.254.169.254/latest/meta-data/'))
            ->toThrow(EndUserException::class, 'Invalid Feed URI');
    } finally {
        if ($prev !== false) {
            \putenv(PrivateAddressGuard::ALLOW_ENV . '=' . $prev);
        }
    }
});

test('private feeds env hatch fails closed on non-1 values', function (): void {
    $prev = \getenv(PrivateAddressGuard::ALLOW_ENV);

    try {
        foreach (['0', '', 'true', 'yes'] as $value) {
            \putenv(PrivateAddressGuard::ALLOW_ENV . '=' . $value);
            expect(PrivateAddressGuard::privateFeedsAllowed())->toBeFalse();
        }
    } finally {
        if ($prev !== false) {
            \putenv(PrivateAddressGuard::ALLOW_ENV . '=' . $prev);
        } else {
            \putenv(PrivateAddressGuard::ALLOW_ENV . '=');
        }
    }
});

test('private address guard accepts bracketed public IPv6 and rejects bracketed private IPv6', function (): void {
    expect(PrivateAddressGuard::hostIsPublic('[2606:4700::1]'))->toBeTrue();
    expect(PrivateAddressGuard::hostIsPublic('[fd00::1]'))->toBeFalse();
    expect(PrivateAddressGuard::hostIsPublic('[::1]'))->toBeFalse();

    PrivateAddressGuard::assertUriHostIsPublic('http://[2606:4700::1]/feed.xml');
    expect(fn () => PrivateAddressGuard::assertUriHostIsPublic('http://[fd00::1]/feed.xml'))
        ->toThrow(EndUserException::class, 'Invalid Feed URI');
    expect(fn () => PrivateAddressGuard::assertUriHostIsPublic('http://[::1]/feed.xml'))
        ->toThrow(EndUserException::class, 'Invalid Feed URI');
});

test('private address guard rejects localhost via resolver records', function (): void {
    // dns_get_record does consult the resolver on this box and returns
    // 127.0.0.1 + ::1 for "localhost", so this is deterministic offline.
    expect(PrivateAddressGuard::hostIsPublic('localhost'))->toBeFalse();
});

test('budgeted socket refuses private and unresolvable destinations before dialing', function (): void {
    $prev = \getenv(PrivateAddressGuard::ALLOW_ENV);
    \putenv(PrivateAddressGuard::ALLOW_ENV);

    try {
        $adapter = new \SimpleNewsletter\Adapters\BudgetedSocket();

        // Redirect-hop enforcement is by construction: laminas calls connect()
        // once per redirect hop, so the guard below runs on every hop; a
        // public-to-private redirect cannot be integration-tested offline.
        expect(fn () => $adapter->connect('192.168.1.1', 80))
            ->toThrow(\Laminas\Http\Client\Adapter\Exception\RuntimeException::class);
        expect(fn () => $adapter->connect('no-such-host.invalid', 80))
            ->toThrow(\Laminas\Http\Client\Adapter\Exception\RuntimeException::class);
    } finally {
        if ($prev !== false) {
            \putenv(PrivateAddressGuard::ALLOW_ENV . '=' . $prev);
        }
    }
});
