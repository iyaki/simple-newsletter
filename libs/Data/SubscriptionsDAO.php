<?php

declare(strict_types=1);

namespace SimpleNewsletter\Data;

use SimpleNewsletter\Components\EndUserException;

final class SubscriptionsDAO
{
    private string $TABLE = 'subscriptions';

    private string $FIELDS_FULL = 'feed_uri, email, active, token_nonce, confirmation_sent_at';

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
            /** @var array{feed_uri: string, email: string, active: string, token_nonce: string, confirmation_sent_at: string}|false $row */
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row === false) {
                return null;
            }

            return $this->SubscriptionDTOFactory($row['feed_uri'], $row['email'], (int) $row['active'], $row['token_nonce'], (int) $row['confirmation_sent_at']);
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
                    :active,
                    :token_nonce,
                    :confirmation_sent_at
                )
                SQL);
            $stmt->execute([
                'feed_uri' => $subscription->feedUri,
                'email' => $subscription->email,
                'active' => (int) $subscription->active,
                'token_nonce' => $subscription->tokenNonce,
                'confirmation_sent_at' => $subscription->confirmationSentAt,
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
            /** @var array<array-key, array{feed_uri: string, email: string, active: string, token_nonce: string, confirmation_sent_at: string}> $result */
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $subscriptions = [];
            foreach ($result as $row) {
                $subscriptions[] = $this->SubscriptionDTOFactory($row['feed_uri'], $row['email'], (int) $row['active'], $row['token_nonce'], (int) $row['confirmation_sent_at']);
            }

            return $subscriptions;
        } catch (\PDOException $pdoException) {
            throw new EndUserException('A technical error occurred. Please try again later.', 0, $pdoException);
        }
    }

    /**
     * Atomically claims the confirmation-resend slot: succeeds only when the
     * last confirmation is older than the interval (new rows, sent-at 0, are
     * always eligible).
     *
     * @throws EndUserException
     */
    public function markConfirmationSent(Subscription $subscription, int $resendIntervalSeconds): bool
    {
        try {
            $now = \time();
            /** @var \PDOStatement $stmt */
            $stmt = $this->db->prepare(<<<SQL
                UPDATE {$this->TABLE}
                SET
                    confirmation_sent_at = :now
                WHERE
                    feed_uri = :feed_uri
                AND email = :email
                AND confirmation_sent_at <= :cutoff
                SQL);
            $stmt->execute([
                'now' => $now,
                'feed_uri' => $subscription->feedUri,
                'email' => $subscription->email,
                'cutoff' => $now - $resendIntervalSeconds,
            ]);

            return $stmt->rowCount() === 1;
        } catch (\PDOException $pdoException) {
            throw new EndUserException('A technical error occurred. Please try again later.', 0, $pdoException);
        }
    }

    private function SubscriptionDTOFactory(
        string $feed_uri,
        string $email,
        int $active,
        string $token_nonce = '',
        int $confirmation_sent_at = 0,
    ): Subscription {
        return new Subscription($feed_uri, $email, (bool) $active, $token_nonce, $confirmation_sent_at);
    }
}
