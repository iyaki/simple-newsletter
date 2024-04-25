<?php

declare(strict_types=1);

namespace SimpleNewsletter;

use SimpleNewsletter\Adapters\FeedImporterLaminas;
use SimpleNewsletter\Adapters\PHPMailerFactory;
use SimpleNewsletter\Adapters\SenderPHPMailer;
use SimpleNewsletter\Components\Auth;
use SimpleNewsletter\Components\EmailTemplateFactory;
use SimpleNewsletter\Components\Sender;
use SimpleNewsletter\Data\Database;
use SimpleNewsletter\Data\FeedsDAO;
use SimpleNewsletter\Data\SubscriptionsDAO;
use SimpleNewsletter\Models\Feeds;
use SimpleNewsletter\Models\Newsletter;
use SimpleNewsletter\Models\Subscriptions;

final class Container
{
    private const DATABASE_COFIG_PATH = __DIR__ . '/../config/database.php';

    private static ?\PDO $database = null;

    private static ?\WeakReference $auth = null;

    private static ?\WeakReference $sender = null;

    private function feeds(): Feeds
    {
        return new Feeds(
            new FeedsDAO($this->database()),
            new FeedImporterLaminas()
        );
    }

    public function subscriptions(): Subscriptions
    {
        return new Subscriptions(
            new SubscriptionsDAO($this->database()),
            $this->feeds(),
            $this->newsletter(),
            $this->auth()
        );
    }

    private function newsletter(): Newsletter
    {
        return new Newsletter(
            $this->sender(),
            $this->emailTemplateFactory(),
            $this->auth()
        );
    }

    private function emailTemplateFactory(): EmailTemplateFactory
    {
        $portToAppend = $_SERVER['SERVER_PORT'] !== '80' && $_SERVER['SERVER_PORT'] !== '443' ? ':' . $_SERVER['SERVER_PORT'] : '';

        return new EmailTemplateFactory(
            $_SERVER['HTTPS'] ? 'https' : 'http' . '://' . $_SERVER['SERVER_NAME'] . $portToAppend
        );
    }

    private function auth(): Auth
    {
        $auth = self::$auth?->get();
        if ($auth instanceof Auth) {
            return $auth;
        }

        $auth = new Auth(\getenv('SECRET_KEY'));
        self::$auth = \WeakReference::create($auth);

        return $auth;
    }

    private function sender(): SenderPHPMailer
    {
        $sender = self::$sender?->get();
        if ($sender instanceof Sender) {
            return $sender;
        }

        $sender = new SenderPHPMailer(
            \getenv('SMTP_HOST'),
            (int) \getenv('SMTP_PORT'),
            \getenv('SMTP_USER'),
            \getenv('SMTP_PASSWORD'),
            \getenv('EMAIL_FROM'),
            \getenv('EMAIL_REPLY_TO')
        );
        self::$sender = \WeakReference::create($sender);

        return $sender;
    }

    private function database(): \PDO
    {
        if (self::$database === null) {
            self::$database = new \PDO((require self::DATABASE_COFIG_PATH)['dsn']);
        }

        return self::$database;
    }

    /**
     * @codeCoverageIgnore
     */
    public function __clone()
    {
        throw new \Exception('Cloning this class is not allowed');
    }

    /**
     * @codeCoverageIgnore
     */
    public function __sleep()
    {
        throw new \Exception('This class can\'t be serialized');
    }
}
