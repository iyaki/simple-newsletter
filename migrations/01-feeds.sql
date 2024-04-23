-- feeds definition

CREATE TABLE feeds (
	uri TEXT NOT NULL PRIMARY KEY,
	title TEXT NOT NULL,
	link TEXT,
	last_update INTEGER NOT NULL,
	trigger_hour INTEGER NOT NULL,
	last_sent_post_uri TEXT
);

CREATE INDEX feeds_trigger_hour_IDX ON feeds (trigger_hour);
