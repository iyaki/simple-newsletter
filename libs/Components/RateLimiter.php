<?php

declare(strict_types=1);

namespace SimpleNewsletter\Components;

final readonly class RateLimiter
{
    private const int WINDOW_SECONDS = 60;
    private const int MAX_REQUESTS = 10;

    public function __construct(
        private \PDO $db
    ) {}

    public function check(string $ip, string $endpoint): void
    {
        $now = \time();
        $windowStart = $now - self::WINDOW_SECONDS;

        // Purge old entries for this IP+endpoint
        $stmt = $this->db->prepare(
            'DELETE FROM rate_limits WHERE ip = :ip AND endpoint = :endpoint AND window_start < :window'
        );
        $stmt->execute(['ip' => $ip, 'endpoint' => $endpoint, 'window' => $windowStart]);

        // Count requests in current window
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM rate_limits WHERE ip = :ip AND endpoint = :endpoint AND window_start >= :window'
        );
        $stmt->execute(['ip' => $ip, 'endpoint' => $endpoint, 'window' => $windowStart]);
        $count = (int) $stmt->fetchColumn();

        if ($count >= self::MAX_REQUESTS) {
            throw new EndUserException('Too many requests. Please try again later.');
        }

        // Record this request
        $stmt = $this->db->prepare(
            'INSERT INTO rate_limits (ip, endpoint, window_start) VALUES (:ip, :endpoint, :window)'
        );
        $stmt->execute(['ip' => $ip, 'endpoint' => $endpoint, 'window' => $now]);
    }
}
