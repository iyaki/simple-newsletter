-- feeds definition

CREATE TABLE feeds (
	uri TEXT NOT NULL,
	title TEXT NOT NULL,
	link TEXT,
	last_update INTEGER NOT NULL,
	trigger_hour INTEGER NOT NULL,
	last_post_uri TEXT,
	last_post_title TEXT,
	CONSTRAINT feeds_pk PRIMARY KEY (uri)
);

CREATE INDEX feeds_trigger_hour_IDX ON feeds (trigger_hour);
