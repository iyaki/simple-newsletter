<?php

declare(strict_types=1);

namespace SimpleNewsletter;

use SimpleNewsletter\Adapters\FeedImporterLaminas;
use SimpleNewsletter\Adapters\PHPMailerFactory;
use SimpleNewsletter\Adapters\SenderPHPMailer;
use SimpleNewsletter\Components\Auth;
use SimpleNewsletter\Data\Database;
use SimpleNewsletter\Data\FeedsDAO;
use SimpleNewsletter\Data\SubscriptionsDAO;
use SimpleNewsletter\Models\Feeds;
use SimpleNewsletter\Models\Subscriptions;

final class Container
{
    private const DATABASE_COFIG_PATH = __DIR__ . '/../config/database.php';

    private static ?\PDO $database = null;

    private function feeds(): Feeds
    {
        return new Feeds(
            new FeedsDAO($this->database()),
            new FeedImporterLaminas()
        );
    }

    public function subscriptions(): Subscriptions
    {
        $portToAppend = $_SERVER['SERVER_PORT'] !== '80' && $_SERVER['SERVER_PORT'] !== '443' ? ':' . $_SERVER['SERVER_PORT'] : '';

        return new Subscriptions(
            new SubscriptionsDAO($this->database()),
            $this->feeds(),
            $this->auth(),
            $this->sender(),
            $_SERVER['HTTPS'] ? 'https' : 'http' . '://' . $_SERVER['SERVER_NAME'] . $portToAppend
        );
    }

    private function auth(): Auth
    {
        return new Auth(\getenv('SECRET_KEY'));
    }

    private function sender(): SenderPHPMailer
    {
        return new SenderPHPMailer(
            new PHPMailerFactory(
                \getenv('SMTP_HOST'),
                (int) \getenv('SMTP_PORT'),
                \getenv('SMTP_USER'),
                \getenv('SMTP_PASSWORD'),
                \getenv('EMAIL_FROM'),
                \getenv('EMAIL_REPLY_TO')
            )
        );
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
