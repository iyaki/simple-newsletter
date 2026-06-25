<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use SimpleNewsletter\Container;

beforeEach(function (): void {
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

test('responder returns ResponderHttp instance', function (): void {
    $container = new Container();
    expect($container->responder())->toBeInstanceOf(\SimpleNewsletter\Adapters\ResponderHttp::class);
});

test(
    'rateLimiter returns RateLimiter instance with PDO',
    /** @throws PDOException */ function (): void {
        $container = new Container();
        expect($container->rateLimiter())->toBeInstanceOf(\SimpleNewsletter\Components\RateLimiter::class);
    },
);

test(
    'subscriptions returns Subscriptions instance',
    /**
     * @throws PDOException
     * @throws PHPMailerException
     */ function (): void {
        $container = new Container();
        expect($container->subscriptions())->toBeInstanceOf(\SimpleNewsletter\Models\Subscriptions::class);
    },
);

test(
    'rateLimiter returns RateLimiter instances',
    /** @throws PDOException */ function (): void {
        $container = new Container();
        $r1 = $container->rateLimiter();
        $r2 = $container->rateLimiter();
        expect($r1)->toBeInstanceOf(\SimpleNewsletter\Components\RateLimiter::class);
        expect($r2)->toBeInstanceOf(\SimpleNewsletter\Components\RateLimiter::class);
    },
);

test(
    'subscriptions returns Subscriptions instances',
    /**
     * @throws PDOException
     * @throws PHPMailerException
     */ function (): void {
        $container = new Container();
        $s1 = $container->subscriptions();
        $s2 = $container->subscriptions();
        expect($s1)->toBeInstanceOf(\SimpleNewsletter\Models\Subscriptions::class);
        expect($s2)->toBeInstanceOf(\SimpleNewsletter\Models\Subscriptions::class);
    },
);

test('container creates independent instances', function (): void {
    $c1 = new Container();
    $c2 = new Container();
    expect($c1->responder())->toBeInstanceOf(\SimpleNewsletter\Adapters\ResponderHttp::class);
    expect($c2->responder())->toBeInstanceOf(\SimpleNewsletter\Adapters\ResponderHttp::class);
});

covers(SimpleNewsletter\Container::class);

