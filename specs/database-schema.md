# Database Schema

Status: Implemented

## Overview

### Purpose

Define the SQLite database schema, constraints, and indexing strategy.

## Data model

### Core Entities

#### feeds

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| uri | TEXT | PRIMARY KEY | Feed URI (canonical) |
| title | TEXT | NOT NULL | Feed title from XML |
| link | TEXT | NOT NULL | Feed website link |
| last_update | TEXT | NOT NULL | ISO 8601 timestamp of last fetch |
| last_post | TEXT | | URI of the most recently sent post |

#### subscriptions

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| feed_uri | TEXT | NOT NULL, FK → feeds(uri) | Subscribed feed |
| email | TEXT | NOT NULL | Subscriber email |
| token | TEXT | NOT NULL | Auth token (HMAC of email) |
| confirmed | INTEGER | NOT NULL DEFAULT 0 | 0=pending, 1=confirmed |
| created_at | TEXT | NOT NULL | ISO 8601 timestamp |
| updated_at | TEXT | NOT NULL | ISO 8601 timestamp |

### Relationships

- One `feed` has many `subscriptions` (1:N via `feed_uri`).
- A subscription targets exactly one feed.

### Persistence Notes

```sql
CREATE TABLE feeds (
    uri         TEXT PRIMARY KEY,
    title       TEXT NOT NULL,
    link        TEXT NOT NULL,
    last_update TEXT NOT NULL,
    last_post   TEXT
);

CREATE TABLE subscriptions (
    feed_uri   TEXT NOT NULL REFERENCES feeds(uri),
    email      TEXT NOT NULL,
    token      TEXT NOT NULL,
    confirmed  INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    PRIMARY KEY (feed_uri, email)
);

CREATE INDEX idx_subscriptions_created_at
    ON subscriptions(created_at);
```

## Workflows

### Migration strategy

Migrations are sequential SQL files in `migrations/`, prefixed with `NN-`.
Applied manually (no migration runner). Production deploys run all migrations in order.

Current migrations:

- `00-setup.sql` — Create feeds + subscriptions tables.
- `01-feeds.sql` — Feed schema refinements.
- `02-subscriptions.sql` — Subscription schema refinements.
- `99-optimizations.sql` — Performance indexes.
