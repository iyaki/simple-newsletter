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
        try {
            $stmt = $this->db->prepare("SELECT {$this->FIELDS_FULL} FROM {$this->TABLE} WHERE feed_uri = :feed_uri AND email = :email");
            $stmt->execute([
                'feed_uri' => $feedUri,
                'email' => $email,
            ]);

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (! \is_array($result)) {
                return null;
            }

            /** @var array{feed_uri: string, email: string, active: string} $result */

            return self::SubscriptionDTOFactory(
                $result['feed_uri'],
                $result['email'],
                (int) $result['active'],
            );
        } catch (\PDOException $e) {
            throw new EndUserException('A technical error occurred. Please try again later.', 0, $e);
        }
    }

    public function activate(Subscription $subscription): void
    {
        try {
            $stmt = $this->db->prepare(<<<SQL
            UPDATE {$this->TABLE}
            SET
                active = 1
            WHERE
                feed_uri = :feed_uri
            AND email = :email
            SQL);
            $stmt->execute([
                'feed_uri' => $subscription->feedUri,
                'email' => $subscription->email,
            ]);
        } catch (\PDOException $e) {
            throw new EndUserException('A technical error occurred. Please try again later.', 0, $e);
        }
    }

    public function deactivate(Subscription $subscription): void
    {
        try {
            $stmt = $this->db->prepare(<<<SQL
            UPDATE {$this->TABLE}
            SET
                active = 0
            WHERE
                feed_uri = :feed_uri
            AND email = :email
            SQL);
            $stmt->execute([
                'feed_uri' => $subscription->feedUri,
                'email' => $subscription->email,
            ]);
        } catch (\PDOException $e) {
            throw new EndUserException('A technical error occurred. Please try again later.', 0, $e);
        }
    }

    public function new(Subscription $subscription): void
    {
        try {
            $stmt = $this->db->prepare(<<<SQL
            INSERT INTO {$this->TABLE} ($this->FIELDS_FULL)
            VALUES (
                :feed_uri,
                :email,
                :active
            )
            SQL);
            $stmt->execute([
                'feed_uri' => $subscription->feedUri,
                'email' => $subscription->email,
                'active' => (int) $subscription->active,
            ]);
        } catch (\PDOException $e) {
            throw new EndUserException('A technical error occurred. Please try again later.', 0, $e);
        }
    }

    /**
     * @return Subscription[]
     */
    /**
     * @return Subscription[]
     */
    public function findActiveSubscriptionsFor(Feed $feed): array
    {
        try {
            $stmt = $this->db->prepare("SELECT {$this->FIELDS_FULL} FROM {$this->TABLE} WHERE feed_uri = :feed_uri AND active = 1");
            $stmt->execute([
                'feed_uri' => $feed->uri,
            ]);

            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (! \is_array($result)) {
                return [];
            }

            return \array_map(function (array $row): Subscription {
                return self::SubscriptionDTOFactory(
                    $row['feed_uri'],
                    $row['email'],
                    (int) $row['active'],
                );
            }, $result);
        } catch (\PDOException $e) {
            throw new EndUserException('A technical error occurred. Please try again later.', 0, $e);
        }
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
