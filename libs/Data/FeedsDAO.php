<?php

declare(strict_types=1);

namespace SimpleNewsletter\Data;

final class FeedsDAO
{
    private string $TABLE = 'feeds';
    private string $FIELDS_FULL = 'uri, title, link, last_update, last_sent_post_uri';

    public function __construct(
        private readonly \PDO $db
    ) {}

    public function find(string $uri): ?Feed
    {
        try {
            $stmt = $this->db->prepare("SELECT {$this->FIELDS_FULL} FROM {$this->TABLE} WHERE uri = :uri");
            $stmt->execute([
                'uri' => $uri,
            ]);

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (! \is_array($result)) {
                return null;
            }

            /** @var array{uri: string, title: string, link: string, last_update: string, last_sent_post_uri: ?string} $result */

            return self::FeedDTOFactory(
                $result['uri'],
                $result['title'],
                $result['link'],
                (int) $result['last_update'],
                $result['last_sent_post_uri'],
            );
        } catch (\PDOException $e) {
            throw new EndUserException('A technical error occurred. Please try again later.', 0, $e);
        }
    }

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
            $stmt->execute([
                'uri' => $feed->uri,
                'title' => $feed->title,
                'last_update' => $feed->lastUpdate->getTimestamp(),
                'last_sent_post_uri' => $feed->lastSentPostUri,
            ]);
        } catch (\PDOException $e) {
            throw new EndUserException('A technical error occurred. Please try again later.', 0, $e);
        }
    }

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
            $stmt->execute([
                'uri' => $feed->uri,
                'title' => $feed->title,
                'link' => $feed->link,
                'last_update' => $feed->lastUpdate->getTimestamp(),
                'trigger_hour' => random_int(0, 23),
            ]);
        } catch (\PDOException $e) {
            throw new EndUserException('A technical error occurred. Please try again later.', 0, $e);
        }
    }

    /**
     * @return Feed[]
     */
    /**
     * @return Feed[]
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
            $stmt->execute([
                'trigger_hour' => (int) $datetime->format('H'),
            ]);

            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (! \is_array($results)) {
                return [];
            }

            return \array_map(function (array $row): Feed {
                return self::FeedDTOFactory(
                    $row['uri'],
                    $row['title'],
                    $row['link'],
                    (int) $row['last_update'],
                    $row['last_sent_post_uri'],
                );
            }, $results);
        } catch (\PDOException $e) {
            throw new EndUserException('A technical error occurred. Please try again later.', 0, $e);
        }
    }

    static private function FeedDTOFactory(
        string $uri,
        string $title,
        string $link,
        int $last_update,
        ?string $last_sent_post_uri,
    ): Feed
    {
        return new Feed(
            $uri,
            $title,
            $link,
            new \DateTimeImmutable('@' . $last_update),
            $last_sent_post_uri,
        );
    }

}
