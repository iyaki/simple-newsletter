-- Feed fetch quarantine: consecutive delivery fetch/parse failures per feed.
-- At the threshold (Feeds::QUARANTINE_THRESHOLD) the feed is skipped for a day
-- between retry probes, turning a permanently broken feed into a single
-- escalation instead of hourly error noise.

ALTER TABLE feeds ADD COLUMN consecutive_failures INTEGER NOT NULL DEFAULT 0;
ALTER TABLE feeds ADD COLUMN last_fetch_attempt INTEGER;
