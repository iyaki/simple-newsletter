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
            → Feeds::retrieveWithPosts() — fetch new posts
            → For each new post:
                → Filter subscribers (confirmed, subscribed to this feed)
                → Newsletter::sendPostToSubscribers()
                    → EmailTemplateFactory::createNewsletter()
                    → Sender::send() → SenderPHPMailer
                → Feeds::updateLastSentPost()
```

### SenderPHPMailer

Uses PHPMailer to send emails via SMTP or local mail transport.
Configured via environment variables.

## Workflows

### 1. Scheduled delivery

1. Cron triggers `bin/send-newsletters.php` every hour.
2. For each feed that has confirmed subscribers:
   - Re-fetch the feed to find new posts.
   - For each new post (since `last_post`):
     - Compose a newsletter email (HTML template).
     - Send individually to each confirmed subscriber.
     - Update `feed.last_post` to the latest sent post's URI.

### 2. Email composition

| Component | Implementation |
|-----------|---------------|
| From address | Configured via env `SMTP_FROM` |
| Subject | `[Feed Title] Post Title` |
| Body | HTML template from `EmailTemplateFactory` |
| Unsubscribe link | Signed cancellation URL with token |
| Per-recipient | Each subscriber gets an individual email (not BCC batch) |

## Configuration

See environment variables in [configuration.md](configuration.md) for SMTP settings.

## Security Considerations

- Unsubscribe tokens are HMAC-signed (same mechanism as confirmation tokens).
- Newsletters must identify the sender and provide a working unsubscribe link per CAN-SPAM compliance.
- Email addresses are stored in plain text in SQLite (no PII encryption currently).
