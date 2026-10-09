-- Resend throttle: timestamp of the last confirmation email per subscription so
-- a pending row cannot trigger unlimited confirmation mail (email bombing).

ALTER TABLE subscriptions ADD COLUMN confirmation_sent_at INTEGER NOT NULL DEFAULT 0;
