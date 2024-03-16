<?php

declare(strict_types=1);

namespace SimpleNewsletter\Data;

final class FeedsDAO
{
    public function __construct(
        private readonly Database $db
    ) {}

    /**
     * @return FeedDTO[]
     */
    public function all(): array
    {
        $stmt = $this->db->prepare('SELECT uri, last_update, last_post FROM feeds');
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_FUNC, self::FeedDTOFactory(...));
    }

    public function save(FeedDTO $feed): void
    {
        $stmt = $this->db->prepare('INSERT INTO feeds (uri, last_update, last_post) VALUES (:uri, :last_update, :last_post)');
        $stmt->execute([
            'uri' => $feed->uri,
            'last_update' => $feed->lastUpdate->getTimestamp(),
            'last_post' => $feed->lastPost,
            'trigger_hour' => \rand(0, 23),
        ]);
    }

    static private function FeedDTOFactory(string $uri, int $lastUpdate, string $lastPost): FeedDTO
    {
        return new FeedDTO(
            $uri,
            new \DateTimeImmutable('@' . $lastUpdate),
            $lastPost
        );
    }

}
