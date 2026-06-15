<?php

declare(strict_types=1);

namespace SimpleNewsletter\Data;

use Random\RandomException;
use SimpleNewsletter\Components\EndUserException;

final class FeedsDAO
{
    private string $TABLE = 'feeds';

    private string $FIELDS_FULL = 'uri, title, link, last_update, last_sent_post_uri';

    public function __construct(
        private readonly \PDO $db,
    ) {}

    /**
     * @throws EndUserException
     */
    public function find(string $uri): ?Feed
    {
        try {
            $stmt = $this->db->prepare(sprintf('SELECT %s FROM %s WHERE uri = :uri', $this->FIELDS_FULL, $this->TABLE));
            if ($stmt === false) {
                throw new EndUserException('A technical error occurred. Please try again later.');
            }

            $stmt->execute([
                'uri' => $uri,
            ]);

            /** @var array{uri: string, title: string, link: string, last_update: string, last_sent_post_uri: ?string}|false $result */
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (! \is_array($result)) {
                return null;
            }

            return $this->FeedDTOFactory(
                $result['uri'],
                $result['title'],
                $result['link'],
                (int) $result['last_update'],
                $result['last_sent_post_uri'],
            );
        } catch (\PDOException $pdoException) {
            throw new EndUserException('A technical error occurred. Please try again later.', 0, $pdoException);
        }
    }

    /**
     * @throws EndUserException
     */
    public function update(Feed $feed): void
    {
        try {
            $stmt = $this->db->prepare(<<<SQL
                UPDATE {$this->TABLE}
                SET
                    title = :title,
                    last_update = :last_update,
                    last_sent_post_uri = :last_sent_post_uri
                WHERE uri = :uri
                SQL);
            if ($stmt === false) {
                throw new EndUserException('A technical error occurred. Please try again later.');
            }

            $stmt->execute([
                'uri' => $feed->getUri(),
                'title' => $feed->getTitle(),
                'last_update' => $feed->getLastUpdate()->getTimestamp(),
                'last_sent_post_uri' => $feed->lastSentPostUri,
            ]);
        } catch (\PDOException $pdoException) {
            throw new EndUserException('A technical error occurred. Please try again later.', 0, $pdoException);
        }
    }

    /**
     * @throws EndUserException
     * @throws RandomException
     */
    public function new(Feed $feed): void
    {
        try {
            $stmt = $this->db->prepare(<<<SQL
                INSERT INTO {$this->TABLE} (
                    uri,
                    title,
                    link,
                    last_update,
                    trigger_hour
                ) VALUES (
                    :uri,
                    :title,
                    :link,
                    :last_update,
                    :trigger_hour
                )
                SQL);
            if ($stmt === false) {
                throw new EndUserException('A technical error occurred. Please try again later.');
            }

            $stmt->execute([
                'uri' => $feed->getUri(),
                'title' => $feed->getTitle(),
                'link' => $feed->getLink(),
                'last_update' => $feed->getLastUpdate()->getTimestamp(),
                'trigger_hour' => random_int(min: 0, max: 23),
            ]);
        } catch (\PDOException $pdoException) {
            throw new EndUserException('A technical error occurred. Please try again later.', 0, $pdoException);
        }
    }

    /**
     * @return Feed[]
     * @throws EndUserException
     */
    public function getScheduled(\DateTimeImmutable $datetime): array
    {
        try {
            $stmt = $this->db->prepare(<<<SQL
                SELECT DISTINCT f.uri, f.title, f.link, f.last_update, f.last_sent_post_uri
                FROM {$this->TABLE} f
                INNER JOIN
                    subscriptions s ON s.feed_uri = f.uri
                WHERE
                    trigger_hour = :trigger_hour
                AND s.active = 1
                SQL);
            if ($stmt === false) {
                throw new EndUserException('A technical error occurred. Please try again later.');
            }

            $stmt->execute([
                'trigger_hour' => (int) $datetime->format('H'),
            ]);

            /** @var array<array{uri: string, title: string, link: string, last_update: string, last_sent_post_uri: ?string}> $results */
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $feeds = [];
            foreach ($results as $row) {
                $feeds[] = $this->FeedDTOFactory(
                    $row['uri'],
                    $row['title'],
                    $row['link'],
                    (int) $row['last_update'],
                    $row['last_sent_post_uri'] ?? null,
                );
            }

            return $feeds;
        } catch (\PDOException $pdoException) {
            throw new EndUserException('A technical error occurred. Please try again later.', 0, $pdoException);
        }
    }

    private function FeedDTOFactory(
        string $uri,
        string $title,
        string $link,
        int $last_update,
        ?string $last_sent_post_uri,
    ): Feed {
        $metadata = new FeedMetadata(
            uri: $uri,
            title: $title,
            link: $link,
            lastUpdate: new \DateTimeImmutable('@' . $last_update),
        );
        return new Feed($metadata, $last_sent_post_uri);
    }
}
