# Repository Guidelines

## Project Overview

**Simple Newsletter** converts Atom/RSS feeds into email newsletters via a double-opt-in subscription flow. Built with FrankenPHP (PHP 8.5 + Caddy, single binary), SQLite, and no framework. Followers subscribe to a feed URI, confirm their email, and receive hourly newsletters when new posts are published. No user accounts, no reader UI — just pull-to-email.

The project follows **Spec-Driven Development (SDD)**: every feature has a corresponding spec in `specs/`. Read specs before implementing.

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

`ResponderHttp` is a standalone HTTP responder with no interface.

## Database Schema

The specs describe an evolved schema not yet migrated (subscriptions: token, confirmed, created_at, updated_at; feeds: trigger_hour missing, last_update semantics, link nullability). The migrations in `migrations/` are the ground truth, not the specs.

## HTTP Routing

No framework router: FrankenPHP serves the filesystem under `public/` directly.

- Landing/terms/privacy serve `index.md` with `Content-Type: text/markdown` when the request accepts it (via `public/serve-markdown.php`); `public/index.php` is the landing page with the subscription form
- API handlers under `public/v1/subscriptions/`: `index.php` (subscribe), `confirmation/`, `cancellation/`
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

## Code Conventions & Patterns

### PHP Mandatory Rules

- `declare(strict_types=1)` in **every** file
- PSR-12 coding style
- `final readonly class` for DTOs, models, and adapters where possible
- Type hints required on all parameters and return types
- `never` return type on entrypoint exit handlers
- PSR-4 autoload: namespace `SimpleNewsletter\`, root `libs/`
- `\` prefix on PHP built-in calls (`\getenv`, `\password_hash`)
- Private constructors + `static` named constructors (`fromString`, `fromEndUserException`) on Template classes
- DAOs use private static factory methods to map DB rows → DTOs

### Dependency Injection

Manual DI via `final class Container` (no framework, no container-interop). Singletons: `$database` as `static ?\PDO`; `$auth` and `$sender` as `static ?\WeakReference` — allows GC between requests in long-running FrankenPHP.

### Error Handling

- `EndUserException extends \Exception` — thrown in Models and Adapters for user-visible errors (invalid URI, bad email, token failure, feed fetch failure); caught in HTTP entrypoints and rendered as 400 responses
- `FeedImporterLaminas` wraps `HttpClientException|FeedException` into `EndUserException`

### Naming

- Private factory methods: lowercase snake_case (`emailTemplateFactory()`, `auth()`)
- DAO methods: `find()`, `new()`, `update()`, `activate()`, `deactivate()`
- SQL: heredoc syntax

## Environment Variables

| Variable | Purpose |
|----------|---------|
| `SECRET_KEY` | Token hashing secret (generated via `openssl rand -hex 32`) |
| `SMTP_HOST`/`PORT`/`USER`/`PASSWORD` | SMTP relay config |
| `SMTP_ENCRYPTION` | tls/ssl (default tls) |
| `SMTP_ALLOW_SELF_SIGNED` | Accept self-signed certs |
| `EMAIL_FROM`/`EMAIL_REPLY_TO` | Sender addresses |
| `URI_SELF` | Canonical base URL (used for confirmation/cancellation/unsubscribe links) |
| `SERVER_NAME` | Caddy server name |
| `SENTRY_DSN` | Error tracking DSN (optional) |

## Important Files

| File | Role |
|------|------|
| `bin/send-newsletters.php` | Cron delivery entrypoint (hourly in prod) |
| `libs/bootstrap.php` | Autoload + Sentry init (prepended via php.ini `auto_prepend_file`) |
| `libs/Models/Feeds.php` | Feed retrieval + caching (24h TTL); broken feeds go to quarantine |

## Runtime/Tooling Preferences

- **Container**: devenv.sh (nix) is the primary dev environment (devcontainer retained as fallback); the production image is a Nix-built OCI image (`infrastructure/image.nix`) derived from the same `devenv.lock` pin

## Testing & QA

- **Framework**: Pest (PHPUnit-based) with 100+ unit/integration tests; e2e tests with Symfony HTTP Client
- **Structure**: `tests/` mirrors `libs/`; e2e tests in `tests/E2e/` (own suite, not in the default run)
- **Database**: In-memory SQLite for unit/integration tests; `data/test-e2e.db` for e2e
- **Patterns**: DAOs test in-memory SQLite; Models mock I/O; e2e tests exercise the full HTTP stack

## Spec-Driven Development Workflow

1. Specs describe intent, not implementation — verify reality in the codebase
2. Update specs only when asked
3. When writing specs, do **not** follow TDD — write the spec first and stop
