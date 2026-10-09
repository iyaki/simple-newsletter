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

test('delivery returns NewsletterDelivery instance', function (): void {
    $container = new Container();
    expect($container->delivery())->toBeInstanceOf(\SimpleNewsletter\Models\NewsletterDelivery::class);
});

test('auth weak reference reuses the live instance and recreates after GC', function (): void {
    $prev = \getenv('SECRET_KEY');
    \putenv('SECRET_KEY=weak-reference-test-secret');
    reset_container_auth_cache();
    $property = new \ReflectionProperty(Container::class, 'auth');

    try {
        $container = new Container();

        $first = $container->subscriptions();
        /** @var \WeakReference|null $weak */
        $weak = $property->getValue(null);
        \assert($weak instanceof \WeakReference, 'auth should be cached in a weak reference');
        $authA = $weak->get();
        \assert($authA instanceof \SimpleNewsletter\Components\Auth, 'cached auth should be live');

        // Still referenced by $first: the next build must reuse the instance.
        $second = $container->subscriptions();
        expect($weak->get())->toBe($authA);

        // Drop every strong reference: the weak reference dies and a fresh
        // Auth is created on the next build (long-running-worker semantics).
        unset($first, $second, $authA);
        \gc_collect_cycles();
        expect($weak->get())->toBeNull();

        $third = $container->subscriptions();
        /** @var \WeakReference|null $rebuilt */
        $rebuilt = $property->getValue(null);
        \assert($rebuilt instanceof \WeakReference, 'auth cache should be repopulated');
        $authC = $rebuilt->get();
        \assert($authC instanceof \SimpleNewsletter\Components\Auth, 'auth should be rebuilt');
        expect($authC)->toBeInstanceOf(\SimpleNewsletter\Components\Auth::class);
        unset($third, $authC, $rebuilt);
    } finally {
        reset_container_auth_cache();
        if ($prev !== false) {
            \putenv('SECRET_KEY=' . $prev);
        }
    }
});

function reset_container_auth_cache(): void
{
    $auth = new \ReflectionProperty(Container::class, 'auth');
    $auth->setValue(null, null);
}

test('container throws when SECRET_KEY is not set', function (): void {
    $prev = \getenv('SECRET_KEY');
    \putenv('SECRET_KEY');
    reset_container_auth_cache();

    try {
        (new Container())->subscriptions();
        $thrown = null;
    } catch (\RuntimeException $exception) {
        $thrown = $exception;
    } finally {
        if ($prev !== false) {
            \putenv('SECRET_KEY=' . $prev);
        }
        reset_container_auth_cache();
    }

    expect($thrown)->not->toBeNull()
        ->and($thrown?->getMessage())->toContain('SECRET_KEY');
});

test('container throws when SECRET_KEY is empty', function (): void {
    $prev = \getenv('SECRET_KEY');
    \putenv('SECRET_KEY=');
    reset_container_auth_cache();

    try {
        (new Container())->subscriptions();
        $thrown = null;
    } catch (\RuntimeException $exception) {
        $thrown = $exception;
    } finally {
        if ($prev !== false) {
            \putenv('SECRET_KEY=' . $prev);
        }
        reset_container_auth_cache();
    }

    expect($thrown)->not->toBeNull()
        ->and($thrown?->getMessage())->toContain('SECRET_KEY');
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

function reset_container_sender_cache(): void
{
    $sender = new \ReflectionProperty(Container::class, 'sender');
    $sender->setValue(null, null);
}

/**
 * @return array<string, array<string, bool>>
 */
function container_sender_smtp_options(): array
{
    $method = new \ReflectionMethod(Container::class, 'sender');
    $sender = $method->invoke(new Container());
    \assert($sender instanceof \SimpleNewsletter\Adapters\SenderPHPMailer);
    $mailer = (new \ReflectionProperty($sender, 'mailer'))->getValue($sender);
    \assert($mailer instanceof \PHPMailer\PHPMailer\PHPMailer);
    $options = $mailer->SMTPOptions;
    \assert(\is_array($options));

    /** @var array<string, array<string, bool>> $options */
    return $options;
}

test('SMTP_ALLOW_SELF_SIGNED=false keeps TLS peer verification enabled', function (): void {
    $prev = \getenv('SMTP_ALLOW_SELF_SIGNED');
    \putenv('SMTP_ALLOW_SELF_SIGNED=false');
    reset_container_sender_cache();

    try {
        expect(container_sender_smtp_options())->toBe([]);
    } finally {
        if ($prev !== false) {
            \putenv('SMTP_ALLOW_SELF_SIGNED=' . $prev);
        }
        reset_container_sender_cache();
    }
});

test('SMTP_ALLOW_SELF_SIGNED=no and off keep TLS peer verification enabled', function (): void {
    $prev = \getenv('SMTP_ALLOW_SELF_SIGNED');
    try {
        foreach (['no', 'off'] as $value) {
            \putenv('SMTP_ALLOW_SELF_SIGNED=' . $value);
            reset_container_sender_cache();
            expect(container_sender_smtp_options())->toBe([]);
        }
    } finally {
        if ($prev !== false) {
            \putenv('SMTP_ALLOW_SELF_SIGNED=' . $prev);
        }
        reset_container_sender_cache();
    }
});

test('SMTP_ALLOW_SELF_SIGNED=true disables TLS peer verification', function (): void {
    $prev = \getenv('SMTP_ALLOW_SELF_SIGNED');
    \putenv('SMTP_ALLOW_SELF_SIGNED=true');
    reset_container_sender_cache();

    try {
        $options = container_sender_smtp_options()['ssl'] ?? [];
        expect($options['verify_peer'] ?? null)->toBeFalse()
            ->and($options['verify_peer_name'] ?? null)->toBeFalse()
            ->and($options['allow_self_signed'] ?? null)->toBeTrue();
    } finally {
        if ($prev !== false) {
            \putenv('SMTP_ALLOW_SELF_SIGNED=' . $prev);
        }
        reset_container_sender_cache();
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
