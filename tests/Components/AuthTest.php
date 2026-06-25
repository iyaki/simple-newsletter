<?php

declare(strict_types=1);

use SimpleNewsletter\Components\Auth;

test('hash returns a non-empty string', function (): void {
    $auth = new Auth('test-secret-for-tests');
    expect($auth->hash('some-key'))->toBeString()->not->toBeEmpty();
});

test('hash is deterministic for same input', function (): void {
    $auth = new Auth('test-secret-for-tests');
    expect($auth->hash('same-key'))->toBe($auth->hash('same-key'));
});

test('verify returns true for matching key and token', function (): void {
    $auth = new Auth('test-secret-for-tests');
    $token = $auth->hash('some-key');
    expect($auth->verify('some-key', $token))->toBeTrue();
});

test('verify returns false for wrong key', function (): void {
    $auth = new Auth('test-secret-for-tests');
    $token = $auth->hash('real-key');
    expect($auth->verify('wrong-key', $token))->toBeFalse();
});

