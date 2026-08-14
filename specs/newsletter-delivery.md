# Newsletter Delivery

Status: Implemented

## Overview

### Purpose

Schedule and send email newsletters containing new posts from subscribed feeds to confirmed subscribers.

## Architecture

### Components

```
bin/send-newsletters.php (CLI entrypoint, called via cron)
    → Subscriptions::sendScheduled()
        → Feeds::getScheduled() — feeds with confirmed subscribers
        → For each feed:
            → Feeds::retrieveWithPosts() — fetch posts
            → Collect posts newer than feed.last_sent_post_uri (watermark); stop at it
            → Filter subscribers (confirmed, subscribed to this feed)
            → Newsletter::sendPostsToSubscribers() — one email per subscriber, all new posts
                → EmailTemplateFactory::createNewsletter()
                → Sender::send() → SenderPHPMailer
            → Feeds::updateLastSentPost() — advance watermark to the newest sent post
```

### SenderPHPMailer

Uses PHPMailer to send emails via SMTP or local mail transport.
Configured via environment variables.

## Workflows

### 1. Scheduled delivery

1. Each feed has a `trigger_hour` that indicates at which hour of the day their newsletters should be sent.
2. Cron triggers `bin/send-newsletters.php` every hour.
3. For each feed whose `trigger_hour` matches the current hour, and has confirmed subscribers:
   - Re-fetch the feed to obtain its posts.
   - Collect every post newer than the watermark (`feed.last_sent_post_uri`); stop at it,
     since it and everything older were already sent.
   - First delivery (`last_sent_post_uri` is null): send only the newest post, not the
     entire historical backlog.
   - Compose a single digest email containing all the new posts.
   - Send one individual email per confirmed subscriber (not a BCC batch).
   - Advance `feed.last_sent_post_uri` to the newest sent post's URI.

### 2. Email composition

| Component | Implementation |
|-----------|---------------|
| From address | Configured via env `SMTP_FROM` |
| Subject | Single post: `Post Title - Feed Title`. Multiple posts: `First Post Title (+N more) - Feed Title` |
| Body | One `<article>` per new post (title links to the original, sanitized HTML content); posts separated by a horizontal rule |
| Unsubscribe link | Signed cancellation URL with token |
| Per-recipient | Each subscriber gets an individual email (not BCC batch) |

## Configuration

See environment variables in [configuration.md](configuration.md) for SMTP settings.

## Security Considerations

- Unsubscribe tokens are HMAC-signed (same mechanism as confirmation tokens).
- Newsletters must identify the sender and provide a working unsubscribe link per CAN-SPAM compliance.
- Email addresses are stored in plain text in SQLite (no PII encryption currently).
