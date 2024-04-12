<?php

declare(strict_types=1);

namespace SimpleNewsletter\Data;

final class FeedsDAO
{
    private string $TABLE = 'feeds';
    private string $FIELDS_FULL = 'uri, title, link, last_update, last_post_uri, last_post_title';

    public function __construct(
        private readonly Database $db
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
        // var_dump($stmt->fetchAll(\PDO::FETCH_FUNC, self::FeedDTOFactory(...)));
    }

    /**
     * @return Feed[]
     */
    public function all(): array
    {
        $stmt = $this->db->prepare("SELECT uri, last_update, last_post FROM {$this->TABLE}");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_FUNC, self::FeedDTOFactory(...));
    }

    public function update(Feed $feed): void
    {
        $stmt = $this->db->prepare(<<<SQL
        UPDATE {$this->TABLE}
        SET
            title = :title,
            last_update = :last_update
        WHERE uri = :uri
        SQL);
        $stmt->execute([
            'uri' => $feed->uri,
            'title' => $feed->title,
            'last_update' => $feed->lastUpdate->getTimestamp(),
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

    static private function FeedDTOFactory(
        string $uri,
        string $title,
        string $link,
        int $last_update,
        ?string $last_post_uri,
        ?string $last_post_title
    ): Feed
    {
        return new Feed(
            $uri,
            $title,
            $link,
            new \DateTimeImmutable('@' . $last_update),
            $last_post_uri,
            $last_post_title
        );
    }

}
