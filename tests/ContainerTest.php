<?php

declare(strict_types=1);

use SimpleNewsletter\Container;

beforeEach(function () {
    putenv('NEWSLETTER_DB_PATH=:memory:');
    putenv('SECRET_KEY=test-secret');
    putenv('SMTP_HOST=localhost');
    putenv('SMTP_PORT=587');
    putenv('SMTP_USER=test');
    putenv('SMTP_PASSWORD=test');
    putenv('EMAIL_FROM=test@example.com');
    putenv('EMAIL_REPLY_TO=test@example.com');
    putenv('URI_SELF=http://localhost');
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
    $getDb = \Closure::bind(fn (): \PDO => $this->database(), $container, Container::class);
    expect($getDb())->toBeInstanceOf(\PDO::class);
});

test('database returns same instance on second call', function () {
    $container = new Container();
    $getDb = \Closure::bind(fn (): \PDO => $this->database(), $container, Container::class);
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
    expect(fn () => $container->__sleep())->toThrow(\Exception::class);
});

test('sender creates and caches same instance on second call', function () {
    $container = new Container();
    $getSender = \Closure::bind(fn () => $this->sender(), $container, Container::class);

    $s1 = $getSender();
    $s2 = $getSender();

    expect($s1)->toBe($s2);
});
