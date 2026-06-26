<?php

declare(strict_types=1);

namespace SimpleNewsletter\Data;

use SimpleNewsletter\Components\EndUserException;

final class SubscriptionsDAO
{
    private string $TABLE = 'subscriptions';

    private string $FIELDS_FULL = 'feed_uri, email, active';

    public function __construct(
        private readonly \PDO $db,
    ) {}

    /** @throws EndUserException */
    public function find(string $feedUri, string $email): ?Subscription
    {
        try {
            /** @var \PDOStatement $stmt */
            $stmt = $this->db->prepare(sprintf(
                'SELECT %s FROM %s WHERE feed_uri = :feed_uri AND email = :email',
                $this->FIELDS_FULL,
                $this->TABLE,
            ));
            $stmt->execute([
                'feed_uri' => $feedUri,
                'email' => $email,
            ]);
            /** @var array{feed_uri: string, email: string, active: string}|false $row */
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row === false) {
                return null;
            }

            return $this->SubscriptionDTOFactory($row['feed_uri'], $row['email'], (int) $row['active']);
        } catch (\PDOException $pdoException) {
            throw new EndUserException('A technical error occurred. Please try again later.', 0, $pdoException);
        }
    }

    /** @throws EndUserException */

    public function activate(Subscription $subscription): void
    {
        try {
            /** @var \PDOStatement $stmt */
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
        } catch (\PDOException $pdoException) {
            throw new EndUserException('A technical error occurred. Please try again later.', 0, $pdoException);
        }
    }

    /** @throws EndUserException */
    public function delete(Subscription $subscription): void
    {
        try {
            /** @var \PDOStatement $stmt */
            $stmt = $this->db->prepare(<<<SQL
                DELETE FROM {$this->TABLE}
                WHERE
                    feed_uri = :feed_uri
                AND email = :email
                SQL);
            $stmt->execute([
                'feed_uri' => $subscription->feedUri,
                'email' => $subscription->email,
            ]);
        } catch (\PDOException $pdoException) {
            throw new EndUserException('A technical error occurred. Please try again later.', 0, $pdoException);
        }
    }

    /** @throws EndUserException */
    public function new(Subscription $subscription): void
    {
        try {
            /** @var \PDOStatement $stmt */
            $stmt = $this->db->prepare(<<<SQL
                INSERT INTO {$this->TABLE} ({$this->FIELDS_FULL})
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
        } catch (\PDOException $pdoException) {
            throw new EndUserException('A technical error occurred. Please try again later.', 0, $pdoException);
        }
    }

    /** @throws EndUserException */

    /**
     * @return Subscription[]
     * @throws EndUserException
     */
    public function findActiveSubscriptionsFor(Feed $feed): array
    {
        try {
            /** @var \PDOStatement $stmt */
            $stmt = $this->db->prepare(sprintf(
                'SELECT %s FROM %s WHERE feed_uri = :feed_uri AND active = 1',
                $this->FIELDS_FULL,
                $this->TABLE,
            ));
            $stmt->execute([
                'feed_uri' => $feed->getUri(),
            ]);
            /** @var array<array-key, array{feed_uri: string, email: string, active: string}> $result */
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $subscriptions = [];
            foreach ($result as $row) {
                $subscriptions[] = $this->SubscriptionDTOFactory($row['feed_uri'], $row['email'], (int) $row['active']);
            }

            return $subscriptions;
        } catch (\PDOException $pdoException) {
            throw new EndUserException('A technical error occurred. Please try again later.', 0, $pdoException);
        }
    }

    private function SubscriptionDTOFactory(
        string $feed_uri,
        string $email,
        int $active,
    ): Subscription {
        return new Subscription($feed_uri, $email, (bool) $active);
    }
}
