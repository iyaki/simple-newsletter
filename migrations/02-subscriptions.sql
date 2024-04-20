-- subscriptions definition

CREATE TABLE subscriptions (
	feed_uri TEXT NOT NULL,
	email TEXT NOT NULL,
	active INTEGER NOT NULL,
	FOREIGN KEY(feed_uri) REFERENCES feeds(uri),
	UNIQUE(feed_uri, email)
);

CREATE INDEX subscriptions_feed_uri_IDX ON subscriptions (feed_uri);
