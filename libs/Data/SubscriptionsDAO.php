<?php

declare(strict_types=1);

namespace SimpleNewsletter\Data;

final class SubscriptionsDAO
{
    private string $TABLE = 'subscriptions';
    private string $FIELDS_FULL = 'feed_uri, email, active';

    public function __construct(
        private readonly Database $db
    ) {}

    public function find(string $feedUri, string $email): ?Subscription
    {
        $stmt = $this->db->prepare("SELECT {$this->FIELDS_FULL} FROM {$this->TABLE} WHERE feed_uri = :feedUri AND email = :email");
        $stmt->execute([
            'feedUri' => $feedUri,
            'email' => $email,
        ]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (! $result) {
            return null;
        }

        return self::SubscriptionDTOFactory(...$result);
    }

    public function activate(Subscription $subscription): void
    {
        $stmt = $this->db->prepare(<<<SQL
        UPDATE {$this->TABLE}
        SET
            active = 1,
        WHERE
            feed_uri = :feedUri
        AND email = :email
        SQL);
        $stmt->execute([
            'feedUri' => $subscription->feedUri,
            'email' => $subscription->email,
        ]);
    }

    public function deactivate(Subscription $feed): void
    {
        $stmt = $this->db->prepare(<<<SQL
        UPDATE {$this->TABLE}
        SET
            active = 1,
        WHERE
            feed_uri = :feedUri
        AND email = :email
        SQL);
        $stmt->execute([
            'feedUri' => $subscription->feedUri,
            'email' => $subscription->email,
        ]);
    }

    public function new(Subscription $subscription): void
    {
        $stmt = $this->db->prepare(<<<SQL
        INSERT INTO {$this->TABLE} ($this->FIELDS_FULL)
        VALUES (
            :feedUri,
            :email,
            :active
        )
        SQL);
        $stmt->execute([
            'feedUri' => $subscription->feedUri,
            'email' => $subscription->email,
            'active' => $subscription->active,
        ]);
    }

    static private function SubscriptionDTOFactory(
        string $feedUri,
        string $email,
        int $active,
    ): Subscription
    {
        return new Subscription(
            $feedUri,
            $email,
            (bool) $active
        );
    }

}
