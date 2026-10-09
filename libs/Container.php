<?php

declare(strict_types=1);

namespace SimpleNewsletter;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use SimpleNewsletter\Adapters\FeedImporterLaminas;
use SimpleNewsletter\Adapters\ResponderHttp;
use SimpleNewsletter\Adapters\SenderPHPMailer;
use SimpleNewsletter\Adapters\SmtpConfig;
use SimpleNewsletter\Components\Auth;
use SimpleNewsletter\Components\EmailTemplateFactory;
use SimpleNewsletter\Components\RateLimiter;
use SimpleNewsletter\Data\FeedsDAO;
use SimpleNewsletter\Data\SubscriptionsDAO;
use SimpleNewsletter\Models\Feeds;
use SimpleNewsletter\Models\Newsletter;
use SimpleNewsletter\Models\NewsletterDelivery;
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

    /** @throws \PDOException */
    private function feeds(): Feeds
    {
        return new Feeds(new FeedsDAO($this->database()), new FeedImporterLaminas());
    }

    /**
     * @throws \PDOException|Exception|\RuntimeException
     */
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

    /**
     * @throws \PDOException|Exception|\RuntimeException
     */
    public function delivery(): NewsletterDelivery
    {
        return new NewsletterDelivery(new SubscriptionsDAO($this->database()), $this->feeds(), $this->newsletter());
    }

    /**
     * @throws Exception|\RuntimeException
     */
    private function newsletter(): Newsletter
    {
        return new Newsletter($this->sender(), $this->emailTemplateFactory(), $this->auth());
    }

    /** @throws \PDOException */
    public function rateLimiter(): RateLimiter
    {
        return new RateLimiter($this->database());
    }

    private function emailTemplateFactory(): EmailTemplateFactory
    {
        $uriSelf = \getenv('URI_SELF');
        return new EmailTemplateFactory(\is_string($uriSelf) ? $uriSelf : '');
    }

    /**
     * @throws \RuntimeException when SECRET_KEY is missing or empty
     */
    private function auth(): Auth
    {
        $auth = self::$auth?->get();
        if ($auth instanceof Auth) {
            return $auth;
        }

        $secretKey = \getenv('SECRET_KEY');
        if (! \is_string($secretKey) || $secretKey === '') {
            // Fail closed: an empty HMAC secret makes consent tokens publicly computable.
            throw new \RuntimeException('SECRET_KEY environment variable must be set to a non-empty secret (openssl rand -hex 32).');
        }
        $auth = new Auth($secretKey);
        self::$auth = \WeakReference::create($auth);

        return $auth;
    }

    /** @throws Exception */
    private function sender(): SenderPHPMailer
    {
        $sender = self::$sender?->get();
        if ($sender instanceof SenderPHPMailer) {
            return $sender;
        }

        $smtpAllowSelfSigned = \getenv('SMTP_ALLOW_SELF_SIGNED');
        // Strict opt-in: only affirmative values may disable TLS peer verification,
        // so documented-looking values like "false" keep verification enabled.
        $config = new SmtpConfig(
            host: ($smtpHost = \getenv('SMTP_HOST')) !== false ? $smtpHost : 'localhost',
            port: (int) (($smtpPort = \getenv('SMTP_PORT')) !== false ? $smtpPort : 587),
            user: ($smtpUser = \getenv('SMTP_USER')) !== false ? $smtpUser : '',
            password: ($smtpPassword = \getenv('SMTP_PASSWORD')) !== false ? $smtpPassword : '',
            from: ($emailFrom = \getenv('EMAIL_FROM')) !== false ? $emailFrom : 'noreply@example.com',
            replyTo: ($emailReplyTo = \getenv('EMAIL_REPLY_TO')) !== false ? $emailReplyTo : 'noreply@example.com',
            encryption: ($smtpEncryption = \getenv('SMTP_ENCRYPTION')) !== false
                ? $smtpEncryption
                : PHPMailer::ENCRYPTION_STARTTLS,
            allowSelfSigned: \in_array(
                \strtolower(\is_string($smtpAllowSelfSigned) ? $smtpAllowSelfSigned : ''),
                ['1', 'true', 'yes', 'on'],
                strict: true,
            ),
        );
        $sender = new SenderPHPMailer($config);
        self::$sender = \WeakReference::create($sender);

        return $sender;
    }

    /** @throws \PDOException */
    private function database(): \PDO
    {
        if (! self::$database instanceof \PDO) {
            /** @var array{dsn: string} $config */
            $config = require __DIR__ . '/../config/database.php';
            self::$database = new \PDO($config['dsn']);
        }

        return self::$database;
    }
}
