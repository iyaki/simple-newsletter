-- feeds definition

CREATE TABLE feeds (
	uri TEXT NOT NULL,
	last_update INTEGER NOT NULL,
	last_post TEXT NOT NULL,
	trigger_hour INTEGER NOT NULL,
	CONSTRAINT feeds_pk PRIMARY KEY (uri)
);
