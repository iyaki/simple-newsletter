<?php

declare(strict_types=1);

namespace SimpleNewsletter\Components;

final readonly class RateLimiter
{
    private const int WINDOW_SECONDS = 60;

    private const int MAX_REQUESTS = 10;

    public function __construct(
        private \PDO $db,
    ) {}

    /**
     * @throws EndUserException
     */
    public function check(string $ip, string $endpoint): void
    {
        try {
            $now = \time();
            $windowStart = $now - self::WINDOW_SECONDS;

            // Global retention sweep: purge every expired row, not just this
            // bucket's, so abandoned ip/endpoint buckets cannot grow the table
            // forever. The only index is (ip, endpoint, window_start), so this
            // sweep is a deliberate full scan; the sweep keeps the table at
            // ~one window of rows, so an extra window_start index would only
            // slow inserts.
            /** @var \PDOStatement $stmt */
            $stmt = $this->db->prepare(
                'DELETE FROM rate_limits WHERE window_start < :window',
            );
            $stmt->execute(['window' => $windowStart]);

            // Count requests in current window
            /** @var \PDOStatement $stmt */
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM rate_limits WHERE ip = :ip AND endpoint = :endpoint AND window_start >= :window',
            );
            $stmt->execute(['ip' => $ip, 'endpoint' => $endpoint, 'window' => $windowStart]);
            $count = (int) $stmt->fetchColumn();

            if ($count >= self::MAX_REQUESTS) {
                throw new EndUserException('Too many requests. Please wait a minute and try again.');
            }

            // Record this request
            /** @var \PDOStatement $stmt */
            $stmt = $this->db->prepare(
                'INSERT INTO rate_limits (ip, endpoint, window_start) VALUES (:ip, :endpoint, :window)',
            );
            $stmt->execute(['ip' => $ip, 'endpoint' => $endpoint, 'window' => $now]);
        } catch (\PDOException $pdoException) {
            throw new EndUserException('A technical error occurred. Please try again later.', 0, $pdoException);
        }
    }
}
