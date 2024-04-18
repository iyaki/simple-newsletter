<?php

declare(strict_types=1);

namespace SimpleNewsletter;

use SimpleNewsletter\Components\Auth;
use SimpleNewsletter\Data\Database;
use SimpleNewsletter\Data\FeedsDAO;
use SimpleNewsletter\Models\Feeds;
use SimpleNewsletter\Models\Subscriptions;

final class Container
{
    private const DATABASE_COFIG_PATH = __DIR__ . '/../config/database.php';

    private static ?Database $database = null;

    public function feeds(): Feeds
    {
        return new Feeds($this->feedsDAO());
    }

    public function subscriptions(): Subscriptions
    {
        return new Subscriptions($this->feedsDAO());
    }

    public function auth(): Auth
    {
        return new Auth(\getenv('SECRET_KEY'));
    }

    private function feedsDAO(): FeedsDAO
    {
        return new FeedsDAO($this->database());
    }

    private function database(): Database
    {
        if (self::$database === null) {
            self::$database = new Database(require self::DATABASE_COFIG_PATH);
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
