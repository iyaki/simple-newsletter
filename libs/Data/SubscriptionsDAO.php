<?php

declare(strict_types=1);

namespace SimpleNewsletter\Data;

final class SubscriptionsDAO
{
    private string $TABLE = 'subscriptions';
    private string $FIELDS_FULL = 'feed_uri, email, active';

    public function __construct(
        private readonly \PDO $db
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
            active = 1
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
            'active' => (int) $subscription->active,
        ]);
    }

    public function findActiveSubscriptionsFor(Feed $feed): array
    {
        $stmt = $this->db->prepare("SELECT {$this->FIELDS_FULL} FROM {$this->TABLE} WHERE feed_uri = :feedUri AND active = 1");
        $stmt->execute(['feedUri' => $feed->uri]);

        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (! $results) {
            return [];
        }

        return \array_map(
            fn (array $row): Subscription => self::SubscriptionDTOFactory(...$row),
            $results
        );
    }

    static private function SubscriptionDTOFactory(
        string $feed_uri,
        string $email,
        int $active,
    ): Subscription
    {
        return new Subscription(
            $feed_uri,
            $email,
            (bool) $active
        );
    }

}
