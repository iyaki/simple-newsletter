CREATE TABLE rate_limits (
    ip TEXT NOT NULL,
    endpoint TEXT NOT NULL,
    window_start INTEGER NOT NULL
);
CREATE INDEX idx_rate_limits_lookup ON rate_limits(ip, endpoint, window_start);
