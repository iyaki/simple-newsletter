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
        $stmt = $this->db->prepare("SELECT {$this->FIELDS_FULL} FROM {$this->TABLE} WHERE uri = :uri");
        $stmt->execute([
            'uri' => $uri,
        ]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (! $result) {
            return null;
        }

        return self::FeedDTOFactory(...$result);
    }

    public function update(Feed $feed): void
    {
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
    }

    public function new(Feed $feed): void
    {
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
            'trigger_hour' => \rand(0, 23),
        ]);
    }

    /**
     * @return Feed[]
     */
    public function getSchedudled(\DateTimeImmutable $datetime): array
    {
        $stmt = $this->db->prepare(<<<SQL
        SELECT f.uri, f.title, f.link, f.last_update, f.last_sent_post_uri
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

        if (! $results) {
            return [];
        }

        return \array_map(
            fn (array $row): Feed => self::FeedDTOFactory(...$row),
            $results
        );
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
