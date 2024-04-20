-- subscriptions definition

CREATE TABLE subscriptions (
	feed_uri TEXT NOT NULL,
	email TEXT NOT NULL,
	active INTEGER NOT NULL
);

CREATE INDEX subscriptions_feed_uri_IDX ON subscriptions (feed_uri);
