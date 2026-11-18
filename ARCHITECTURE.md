# Architecture

How Simple Newsletter is built: layer stack, data flows, database schema, HTTP routing. Coding rules live in `CODING_STANDARDS.md`; commands in `README.md` § Development, deploy in `RUNBOOK.md`.

## Layer Stack (dependencies flow upward)

```
Data/ (DTOs + DAOs)          ← stdlib only (\PDO)
  ↑
Templates/ (rendering)        ← Data DTOs + EndUserException
  ↑
Components/ (interfaces+utils) ← contracts for Adapters
  ↑
Adapters/ (I/O impls)          ← implements Components interfaces
  ↑
Models/ (domain orchestration) ← wires DAOs + Components + Adapters
  ↑
Container.php (manual DI)      ← top-level wiring
```

`libs/bootstrap.php` (autoload + Sentry init) is prepended to every request via php.ini `auto_prepend_file`.

## Two Main Data Flows

**Subscription** (double-opt-in): HTTP handler → `Subscriptions::add()` → `Feeds::retrieve()` (fetch/cache feed) → `SubscriptionsDAO::new()` → `Newsletter::sendConfirmation()` (Auth::hash → templates constructed inline → SenderPHPMailer::send)

**Delivery** (hourly cron): `bin/send-newsletters.php` → `Container::delivery()` → `NewsletterDelivery::sendScheduled()` → `Feeds::getScheduled()` → `FeedImporterLaminas::fetchWithPosts()` → `Newsletter::sendPostsToSubscribers()` per subscriber → `Feeds::updateLastSentPost()`

Feed fetching and caching (24h TTL) live in `libs/Models/Feeds.php`; broken feeds go to quarantine.

## Database Schema

The specs describe an evolved schema not yet migrated (subscriptions: token, confirmed, created_at, updated_at; feeds: trigger_hour missing, last_update semantics, link nullability). The migrations in `migrations/` are the ground truth, not the specs.

## HTTP Routing

No framework router: FrankenPHP serves the filesystem under `public/` directly.

- Landing/terms/privacy serve `index.md` with `Content-Type: text/markdown` when the request accepts it (via `public/serve-markdown.php`)
- Content-negotiation via `Accept` header (`application/json` → JSON, else HTML); redirect mode on `redirect` param
