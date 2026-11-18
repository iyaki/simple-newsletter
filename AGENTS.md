# Repository Guidelines

## Project Overview

**Simple Newsletter** converts Atom/RSS feeds into email newsletters via a double-opt-in subscription flow. Built with FrankenPHP (PHP 8.5 + Caddy, single binary), SQLite, and no framework. Followers subscribe to a feed URI, confirm their email, and receive hourly newsletters when new posts are published. No user accounts, no reader UI — just pull-to-email.

The project follows **Spec-Driven Development (SDD)**: every feature has a corresponding spec in `specs/`.

## Coding Standards

`CODING_STANDARDS.md` holds the PHP, testing, and spec-workflow rules — open it before editing any PHP or test file, or writing a spec.

## Architecture & Data Flow

### Layer Stack (dependencies flow upward)

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

### Two Main Data Flows

**Subscription** (double-opt-in): HTTP handler → `Subscriptions::add()` → `Feeds::retrieve()` (fetch/cache feed) → `SubscriptionsDAO::new()` → `Newsletter::sendConfirmation()` (Auth::hash → templates constructed inline → SenderPHPMailer::send)

**Delivery** (hourly cron): `bin/send-newsletters.php` → `Container::delivery()` → `NewsletterDelivery::sendScheduled()` → `Feeds::getScheduled()` → `FeedImporterLaminas::fetchWithPosts()` → `Newsletter::sendPostsToSubscribers()` per subscriber → `Feeds::updateLastSentPost()`

## Database Schema

The specs describe an evolved schema not yet migrated (subscriptions: token, confirmed, created_at, updated_at; feeds: trigger_hour missing, last_update semantics, link nullability). The migrations in `migrations/` are the ground truth, not the specs.

## HTTP Routing

No framework router: FrankenPHP serves the filesystem under `public/` directly.

- Landing/terms/privacy serve `index.md` with `Content-Type: text/markdown` when the request accepts it (via `public/serve-markdown.php`)
- Content-negotiation via `Accept` header (`application/json` → JSON, else HTML); redirect mode on `redirect` param

## Development Commands

```bash
# Start dev environment (frankenphp worker + mailpit: dev SMTP sink on :1025, UI :8025)
devenv up

# Run unit/integration tests
devenv shell -- composer test

# Run e2e tests (self-contained: own server on :8082, SMTP mock on :1026)
devenv shell -- ./scripts/run-e2e-tests.sh

# Build production image locally (OCI tarball from the devenv.lock pin)
devenv shell -- scripts/build-image.sh

# Build+push production image to GHCR (manual path; CI publishes on green main)
devenv shell -- scripts/build-push.sh

# Deploy production (pulls ghcr.io/iyaki/simple-newsletter:latest)
docker compose -f compose-prod.yaml up --pull always -d
```

## Important Files

| File | Role |
|------|------|
| `libs/bootstrap.php` | Autoload + Sentry init (prepended via php.ini `auto_prepend_file`) |
| `libs/Models/Feeds.php` | Feed retrieval + caching (24h TTL) |

## Runtime/Tooling Preferences

- **Container**: devenv.sh (nix) is the primary dev environment (devcontainer retained as fallback); the production image is a Nix-built OCI image (`infrastructure/image.nix`) derived from the same `devenv.lock` pin
