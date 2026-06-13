<?php

declare(strict_types=1);

use SimpleNewsletter\Components\Auth;

test('hash and verify return true for same key', function () {
    $auth = new Auth('test-secret');
    $token = $auth->hash('user@example.com');
    expect($auth->verify('user@example.com', $token))->toBeTrue();
});

test('verify returns false for different key', function () {
    $auth = new Auth('test-secret');
    $token = $auth->hash('user@example.com');
    expect($auth->verify('other@example.com', $token))->toBeFalse();
});

test('hash is deterministic', function () {
    $auth = new Auth('test-secret');
    expect($auth->hash('user@example.com'))->toEqual($auth->hash('user@example.com'));
});
