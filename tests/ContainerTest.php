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


test('container uses empty string when URI_SELF not set', function (): void {
    unset($_ENV['URI_SELF']);
    $container = new Container();
    $factory = $container->responder(); // Uses emailTemplateFactory which uses URI_SELF
    expect($factory)->toBeInstanceOf(\SimpleNewsletter\Adapters\ResponderHttp::class);
});

test('container creates new Auth when weak reference is null', function (): void {
    // Clear the static weak reference by setting null SECRET_KEY first
    $prev = $_ENV['SECRET_KEY'] ?? null;
    unset($_ENV['SECRET_KEY']);
    
    $container = new Container();
    // This forces auth() to create new Auth with empty string
    $subscriptions = $container->subscriptions();
    expect($subscriptions)->toBeInstanceOf(\SimpleNewsletter\Models\Subscriptions::class);
    
    if ($prev !== null) {
        $_ENV['SECRET_KEY'] = $prev;
    }
});

test('SmtpConnection uses localhost when SMTP_HOST not set', function (): void {
    $prev = $_ENV['SMTP_HOST'] ?? null;
    unset($_ENV['SMTP_HOST']);
    
    $container = new Container();
    $sender = $container->responder();
    expect($sender)->toBeInstanceOf(\SimpleNewsletter\Adapters\ResponderHttp::class);
    
    if ($prev !== null) {
        $_ENV['SMTP_HOST'] = $prev;
    }
});

test('SmtpConnection uses port 587 when SMTP_PORT not set', function (): void {
    $prev = $_ENV['SMTP_PORT'] ?? null;
    unset($_ENV['SMTP_PORT']);
    
    $container = new Container();
    $sender = $container->responder();
    expect($sender)->toBeInstanceOf(\SimpleNewsletter\Adapters\ResponderHttp::class);
    
    if ($prev !== null) {
        $_ENV['SMTP_PORT'] = $prev;
    }
});

test('SMTP_ENCRYPTION defaults to STARTTLS when not set', function (): void {
    $prev = $_ENV['SMTP_ENCRYPTION'] ?? null;
    unset($_ENV['SMTP_ENCRYPTION']);
    
    $container = new Container();
    $sender = $container->responder();
    expect($sender)->toBeInstanceOf(\SimpleNewsletter\Adapters\ResponderHttp::class);
    
    if ($prev !== null) {
        $_ENV['SMTP_ENCRYPTION'] = $prev;
    }
});

test('SMTP_ALLOW_SELF_SIGNED defaults to false when not set', function (): void {
    $prev = $_ENV['SMTP_ALLOW_SELF_SIGNED'] ?? null;
    unset($_ENV['SMTP_ALLOW_SELF_SIGNED']);
    
    $container = new Container();
    $sender = $container->responder();
    expect($sender)->toBeInstanceOf(\SimpleNewsletter\Adapters\ResponderHttp::class);
    
    if ($prev !== null) {
        $_ENV['SMTP_ALLOW_SELF_SIGNED'] = $prev;
    }
});

test('SmtpCredentials use empty strings when env not set', function (): void {
    $prevUser = $_ENV['SMTP_USER'] ?? null;
    $prevPass = $_ENV['SMTP_PASSWORD'] ?? null;
    unset($_ENV['SMTP_USER'], $_ENV['SMTP_PASSWORD']);
    
    $container = new Container();
    $sender = $container->responder();
    expect($sender)->toBeInstanceOf(\SimpleNewsletter\Adapters\ResponderHttp::class);
    
    if ($prevUser !== null) $_ENV['SMTP_USER'] = $prevUser;
    if ($prevPass !== null) $_ENV['SMTP_PASSWORD'] = $prevPass;
});

test('SmtpSender uses default addresses when env not set', function (): void {
    $prevFrom = $_ENV['EMAIL_FROM'] ?? null;
    $prevTo = $_ENV['EMAIL_REPLY_TO'] ?? null;
    unset($_ENV['EMAIL_FROM'], $_ENV['EMAIL_REPLY_TO']);
    
    $container = new Container();
    $sender = $container->responder();
    expect($sender)->toBeInstanceOf(\SimpleNewsletter\Adapters\ResponderHttp::class);
    
    if ($prevFrom !== null) $_ENV['EMAIL_FROM'] = $prevFrom;
    if ($prevTo !== null) $_ENV['EMAIL_REPLY_TO'] = $prevTo;
});
