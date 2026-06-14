<?php

declare(strict_types=1);

use SimpleNewsletter\Container;

beforeEach(function () {
    $_ENV['NEWSLETTER_DB_PATH'] = ':memory:';
    $_ENV['SECRET_KEY'] = 'test-secret';
    $_ENV['SMTP_HOST'] = 'localhost';
    $_ENV['SMTP_PORT'] = '587';
    $_ENV['SMTP_USER'] = 'test';
    $_ENV['SMTP_PASSWORD'] = 'test';
    $_ENV['EMAIL_FROM'] = 'test@example.com';
    $_ENV['EMAIL_REPLY_TO'] = 'test@example.com';
    $_ENV['URI_SELF'] = 'http://localhost';
});

test('responder returns ResponderHttp instance', function () {
    $container = new Container();
    expect($container->responder())->toBeInstanceOf(\SimpleNewsletter\Adapters\ResponderHttp::class);
});

test('rateLimiter returns RateLimiter instance with PDO', function () {
    $container = new Container();
    expect($container->rateLimiter())->toBeInstanceOf(\SimpleNewsletter\Components\RateLimiter::class);
});

test('subscriptions returns Subscriptions instance', function () {
    $container = new Container();
    expect($container->subscriptions())->toBeInstanceOf(\SimpleNewsletter\Models\Subscriptions::class);
});

test('database returns PDO instance', function () {
    $container = new Container();
    $getDb = \Closure::bind(method: $container->database(...), new: null, scope: Container::class);
    expect($getDb())->toBeInstanceOf(\PDO::class);
});

test('database returns same instance on second call', function () {
    $container = new Container();
    $getDb = \Closure::bind(method: $container->database(...), new: null, scope: Container::class);
    $db1 = $getDb();
    $db2 = $getDb();
    expect($db1)->toBe($db2);
});

test('__clone throws Exception', function () {
    $container = new Container();
    expect(fn () => clone $container)->toThrow(\Exception::class);
});

test('__sleep throws Exception', function () {
    $container = new Container();
    expect(fn () => \Closure::bind(method: $container->__sleep(...), new: $container, scope: Container::class)())
        ->toThrow(\Exception::class);
});

test('sender creates and caches same instance on second call', function () {
    $container = new Container();
    $getSender = \Closure::bind(method: $container->sender(...), new: null, scope: Container::class);

    $s1 = $getSender();
    $s2 = $getSender();

    expect($s1)->toBe($s2);
});
