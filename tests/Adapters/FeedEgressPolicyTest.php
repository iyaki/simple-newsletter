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
