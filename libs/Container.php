<?php

declare(strict_types=1);

namespace SimpleNewsletter;

use PHPMailer\PHPMailer\PHPMailer;
use SimpleNewsletter\Adapters\FeedImporterLaminas;
use SimpleNewsletter\Adapters\ResponderHttp;
use SimpleNewsletter\Adapters\SenderPHPMailer;
use SimpleNewsletter\Adapters\SmtpConfig;
use SimpleNewsletter\Adapters\SmtpConnection;
use SimpleNewsletter\Adapters\SmtpCredentials;
use SimpleNewsletter\Adapters\SmtpSender;
use SimpleNewsletter\Components\Auth;
use SimpleNewsletter\Components\EmailTemplateFactory;
use SimpleNewsletter\Components\RateLimiter;
use SimpleNewsletter\Data\FeedsDAO;
use SimpleNewsletter\Data\SubscriptionsDAO;
use SimpleNewsletter\Models\Feeds;
use SimpleNewsletter\Models\Newsletter;
use SimpleNewsletter\Models\Subscriptions;

/**
 * Container for dependency injection
 *
 * @mago-ignore too-many-methods
 */
final class Container
{
    private static ?\PDO $database = null;

    /** @var \WeakReference<Auth> */
    private static ?\WeakReference $auth = null;

    /** @var \WeakReference<SenderPHPMailer> */
    private static ?\WeakReference $sender = null;

    private function feeds(): Feeds
    {
        return new Feeds(new FeedsDAO($this->database()), new FeedImporterLaminas());
    }

    public function subscriptions(): Subscriptions
    {
        return new Subscriptions(
            new SubscriptionsDAO($this->database()),
            $this->feeds(),
            $this->newsletter(),
            $this->auth(),
        );
    }

    public function responder(): ResponderHttp
    {
        return new ResponderHttp();
    }

    private function newsletter(): Newsletter
    {
        return new Newsletter($this->sender(), $this->emailTemplateFactory(), $this->auth());
    }

    public function rateLimiter(): RateLimiter
    {
        return new RateLimiter($this->database());
    }

    private function emailTemplateFactory(): EmailTemplateFactory
    {
        return new EmailTemplateFactory(\getenv('URI_SELF') ?? '');
    }

    private function auth(): Auth
    {
        $auth = self::$auth?->get();
        if ($auth instanceof Auth) {
            return $auth;
        }

        $auth = new Auth(\getenv('SECRET_KEY') ?? '');
        self::$auth = \WeakReference::create($auth);

        return $auth;
    }

    private function sender(): SenderPHPMailer
    {
        $sender = self::$sender?->get();
        if ($sender instanceof SenderPHPMailer) {
            return $sender;
        }

        $connection = new SmtpConnection(
            host: \getenv('SMTP_HOST') ?? 'localhost',
            port: (int) (\getenv('SMTP_PORT') ?? 587),
            encryption: \getenv('SMTP_ENCRYPTION') ?? PHPMailer::ENCRYPTION_STARTTLS,
            allowSelfSigned: (bool) (\getenv('SMTP_ALLOW_SELF_SIGNED') ?? false),
        );
        $credentials = new SmtpCredentials(user: \getenv('SMTP_USER') ?? '', password: \getenv('SMTP_PASSWORD') ?? '');
        $senderConfig = new SmtpSender(
            from: \getenv('EMAIL_FROM') ?? 'noreply@example.com',
            replyTo: \getenv('EMAIL_REPLY_TO') ?? 'noreply@example.com',
        );
        $config = new SmtpConfig($connection, $credentials, $senderConfig);
        $sender = new SenderPHPMailer($config);
        self::$sender = \WeakReference::create($sender);

        return $sender;
    }

    private function database(): \PDO
    {
        if (! self::$database instanceof \PDO) {
            self::$database = new \PDO((require self::DATABASE_COFIG_PATH)['dsn']);
        }

        return self::$database;
    }
}
