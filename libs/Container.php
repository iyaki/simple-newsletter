<?php

declare(strict_types=1);

namespace SimpleNewsletter;

use PHPMailer\PHPMailer\Exception;
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

    /** @throws \PDOException */
    private function feeds(): Feeds
    {
        return new Feeds(new FeedsDAO($this->database()), new FeedImporterLaminas());
    }

    /** @throws \PDOException|Exception */
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

    /** @throws Exception */
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

    private function auth(): Auth
    {
        $auth = self::$auth?->get();
        if ($auth instanceof Auth) {
            return $auth;
        }

        $secretKey = \getenv('SECRET_KEY');
        $auth = new Auth(\is_string($secretKey) ? $secretKey : '');
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

        $connection = new SmtpConnection(
            host: ($smtpHost = \getenv('SMTP_HOST')) !== false ? $smtpHost : 'localhost',
            port: (int) (($smtpPort = \getenv('SMTP_PORT')) !== false ? $smtpPort : 587),
            encryption: ($smtpEncryption = \getenv('SMTP_ENCRYPTION')) !== false
                ? $smtpEncryption
                : PHPMailer::ENCRYPTION_STARTTLS,
            allowSelfSigned: (bool) (
                ($smtpAllowSelfSigned = \getenv('SMTP_ALLOW_SELF_SIGNED')) !== false ? $smtpAllowSelfSigned : false
            ),
        );
        $credentials = new SmtpCredentials(
            user: ($smtpUser = \getenv('SMTP_USER')) !== false ? $smtpUser : '',
            password: ($smtpPassword = \getenv('SMTP_PASSWORD')) !== false ? $smtpPassword : '',
        );
        $senderConfig = new SmtpSender(
            from: ($emailFrom = \getenv('EMAIL_FROM')) !== false ? $emailFrom : 'noreply@example.com',
            replyTo: ($emailReplyTo = \getenv('EMAIL_REPLY_TO')) !== false ? $emailReplyTo : 'noreply@example.com',
        );
        $config = new SmtpConfig($connection, $credentials, $senderConfig);
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
