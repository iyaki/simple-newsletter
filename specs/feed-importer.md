# Feed Importer

Status: Implemented

## Overview

### Purpose

Fetch, parse, and cache Atom and RSS feeds from remote servers. Extract post metadata for newsletter generation.

## Architecture

### Components

```
FeedImporter (interface) ← FeedImporterLaminas (adapter)
    ↓
Feeds (model — orchestration layer)
    ↓
FeedsDAO (persistence)
```

### FeedImporterLaminas

Uses `laminas/laminas-feed` Reader to parse Atom 1.0 and RSS 2.0 feeds.
Uses `laminas/laminas-http` Client for HTTP fetching.

## Data model

### Feed value object (libs/Data/Feed.php)

| Field | Type | Description |
|-------|------|-------------|
| uri | string | Canonical feed URI |
| title | string | Extracted feed title |
| link | string | Feed website link |
| lastUpdate | DateTimeImmutable | Timestamp of last fetch |
| lastPost | ?string | URI of most recently sent post |

### Post value object (libs/Data/Post.php)

| Field | Type | Description |
|-------|------|-------------|
| uri | string | Post permalink |
| title | string | Post title |
| content | string | Post body (sanitized HTML) |
| updated | DateTimeImmutable | Last update timestamp of post |

## Workflows

### 1. Fetch new feed

1. `FeedImporter::fetchNew(uri: string): Feed`
2. Fetch feed XML over HTTP.
3. Parse with Laminas Feed Reader.
4. Extract title, link, last update timestamp.
5. Return Feed value object (no posts loaded).

### 2. Fetch existing feed with posts

1. `FeedImporter::fetchWithPosts(feed: Feed): Feed`
2. Re-fetch feed XML.
3. Parse all entries.
4. Filter posts newer than `feed.lastPost`.
5. Return updated Feed with `posts` array.

### 3. Update stale feed

1. `Feeds::retrieve(uri: string): Feed`
2. Check cache: if `feed.lastUpdate` < 24h ago, return cached.
3. If stale, call `fetch()` to re-import metadata.
4. Update via `FeedsDAO::update()`.

## Dependencies

- **laminas/laminas-feed**: Feed parsing (Atom/RSS).
- **laminas/laminas-http**: HTTP client for fetching feed XML.
