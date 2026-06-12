# Core Architecture

Status: Implemented

## Overview

### Purpose

Define the high-level architecture, module layout, and data flow of the Simple Newsletter system.

## Architecture

### Module/package layout

```
/
├── public/              # HTTP entrypoints (FrankenPHP)
│   ├── index.php        # Landing page
│   ├── api-spec.yaml    # OpenAPI 3.0 spec
│   └── v1/
│       └── subscriptions/
│           ├── index.php         # POST/GET subscription request
│           ├── confirmation/     # Confirm subscription
│           └── cancellation/     # Cancel subscription
├── libs/
│   ├── Container.php              # Manual DI container
│   ├── bootstrap.php              # Autoload + Sentry init
│   ├── Models/
│   │   ├── Feeds.php              # Feed retrieval & caching logic
│   │   ├── Newsletter.php         # Email composition & sending
│   │   └── Subscriptions.php      # Subscription workflow orchestration
│   ├── Components/
│   │   ├── Auth.php               # Token hashing for confirmation/cancellation
│   │   ├── EmailTemplateFactory.php
│   │   ├── EndUserException.php
│   │   ├── FeedImporter.php       # Interface for feed fetching
│   │   └── Sender.php             # Interface for email sending
│   ├── Adapters/
│   │   ├── FeedImporterLaminas.php # Laminas-feed implementation
│   │   ├── ResponderHttp.php       # HTTP response builder
│   │   └── SenderPHPMailer.php     # PHPMailer implementation
│   ├── Data/
│   │   ├── Database.php            # PDO connection wrapper
│   │   ├── Feed.php                # Feed value object
│   │   ├── Post.php                # Post value object
│   │   ├── Subscription.php        # Subscription value object
│   │   ├── FeedsDAO.php            # Feed data access
│   │   └── SubscriptionsDAO.php    # Subscription data access
│   └── Templates/
│       ├── ApiV1/                  # API response templates
│       └── Email/                  # Email HTML templates
├── bin/
│   └── send-newsletters.php        # CLI entrypoint for scheduled delivery
├── config/
│   └── database.php                # SQLite path config
├── migrations/                     # SQL schema migrations
├── data/
│   └── database.sqlite3            # SQLite database file
└── .php/                           # PHP configuration overrides
```

### Data flow summary

```
Subscription flow:
  User → public/v1/subscriptions/index.php
      → Subscriptions::add()
          → Feeds::retrieve() → FeedImporter::fetch/fetchNew() → FeedsDAO
          → SubscriptionsDAO::create()
          → Newsletter::sendConfirmation()
              → Auth::hash() for token
              → EmailTemplateFactory::createConfirmation()
              → Sender::send() → SenderPHPMailer

Delivery flow:
  cron → bin/send-newsletters.php
      → Subscriptions::sendScheduled()
          → Feeds::getScheduled()
          → For each feed with subscriptions:
              → Feeds::retrieveWithPosts() → FeedImporter::fetchWithPosts()
              → For each new post:
                  → Newsletter::sendPostToSubscribers()
                  → Feeds::updateLastSentPost()
```

## Security Considerations

- Confirmation/cancellation tokens are HMAC hashes of the subscriber email.
- No passwords, no session management.
- SQLite file permissions must be restricted.
