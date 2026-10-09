-- Token binding: per-subscription nonce included in the confirm/cancel MAC so
-- a leaked link cannot be replayed against re-created rows, and backfill for
-- rows created before this column existed.

ALTER TABLE subscriptions ADD COLUMN token_nonce TEXT NOT NULL DEFAULT '';

UPDATE subscriptions SET token_nonce = lower(hex(randomblob(16)));
