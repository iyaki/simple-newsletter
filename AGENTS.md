# Repository Guidelines

## Project Overview

**Simple Newsletter** converts Atom/RSS feeds into email newsletters via a double-opt-in subscription flow. Built with FrankenPHP (PHP 8.3 + Caddy, single binary), SQLite, and no framework. Followers subscribe to a feed URI, confirm their email, and receive hourly newsletters when new posts are published. No user accounts, no reader UI — just pull-to-email.

The project follows **Spec-Driven Development (SDD)**: every feature has a corresponding spec in `specs/`. Read specs before implementing.

## Architecture & Data Flow

### Layer Stack (dependencies flow upward)

```
Data/ (DTOs + DAOs)          ← stdlib only (\\PDO)
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

**Subscription** (double-opt-in): HTTP handler → `Subscriptions::add()` → `Feeds::retrieve()` (fetch/cache feed) → `SubscriptionsDAO::new()` → `Newsletter::sendConfirmation()` (Auth::hash → EmailTemplateFactory → Sender::send)

**Delivery** (hourly cron): `bin/send-newsletters.php` → `Subscriptions::sendScheduled()` → `Feeds::getScheduled()` → `FeedImporter::fetchWithPosts()` → `Newsletter::sendPostToSubscribers()` per subscriber → `Feeds::updateLastSentPost()`

### Interface/Implementation Split

| Interface | Implementations |
|-----------|----------------|
| `Components\FeedImporter` | `Adapters\FeedImporterLaminas` (laminas-feed + laminas-http) |
| `Components\Sender` | `Adapters\SenderPHPMailer` (PHPMailer SMTP) |
| `Templates\ApiV1\ResponseInterface` | `JsonResponse`, `HtmlResponse`, `RedirectResponse` |
| `Templates\Email\EmailInterface` | `Newsletter`, `SubscriptionConfirmation` |

`ResponderHttp` is a standalone HTTP responder with no interface. EndUserException is the user-facing error signal — caught in HTTP entrypoints and rendered as error responses.

### Database Schema (actual from migrations)

**feeds**: `uri` (TEXT PK), `title` (TEXT NOT NULL), `link` (TEXT nullable), `last_update` (INTEGER — Unix timestamp), `trigger_hour` (INTEGER 0-23 for load distribution), `last_sent_post_uri` (TEXT nullable). Index on `trigger_hour`.

**subscriptions**: `feed_uri` (TEXT NOT NULL → FK feeds.uri), `email` (TEXT NOT NULL), `active` (INTEGER NOT NULL boolean). UNIQUE(feed_uri, email). Index on `feed_uri`.

> **Note**: The specs describe an evolved schema not yet migrated (subscriptions: token, confirmed, created_at, updated_at; feeds: last_post). The 6-month-old migrations are the ground truth.

## Key Directories

| Path | Purpose |
|------|---------|
| `public/` | HTTP entrypoints — served directly by FrankenPHP (no router) |
| `public/v1/subscriptions/` | API handlers: `index.php` (subscribe), `confirmation/`, `cancellation/` |
| `bin/` | CLI entrypoints — `send-newsletters.php` (cron trigger) |
| `libs/Models/` | Domain orchestration — `Feeds`, `Subscriptions`, `Newsletter` |
| `libs/Components/` | Interfaces and utilities — `FeedImporter`, `Sender`, `Auth`, `EmailTemplateFactory`, `EndUserException` |
| `libs/Adapters/` | I/O implementations — `FeedImporterLaminas`, `SenderPHPMailer`, `ResponderHttp` |
| `libs/Data/` | DAOs and DTOs — `FeedsDAO`, `SubscriptionsDAO`, `Feed`, `Post`, `Subscription` |
| `libs/Templates/` | Renderers — `ApiV1/` (JSON/HTML/Redirect), `Email/` (Newsletter, SubscriptionConfirmation) |
| `migrations/` | Sequential SQL schema migrations (00-setup, 01-feeds, 02-subscriptions, 99-optimize) |
| `specs/` | Technical specs (authoritative design reference) |
| `config/` | `database.php` — returns PDO DSN array |
| `scripts/` | Build and deploy helpers |
| `data/` | SQLite database file (gitignored) |

## HTTP Routing

No framework router. FrankenPHP serves the filesystem directly:

- `GET /` → `public/index.php` (landing page)
- `GET|POST /v1/subscriptions/` → `public/v1/subscriptions/index.php` (query: uri, email, return, redirect)
- `GET /v1/subscriptions/confirmation/` → confirmation handler (query: uri, email, token)
- `GET /v1/subscriptions/cancellation/` → cancellation handler (query: uri, email, token)
- Content-negotiation via `Accept` header (`application/json` → JSON, else HTML); redirect mode on `redirect` param

## Development Commands

```bash
# Start dev environment (with auto-reload)
docker compose up

# Run unit/integration tests
docker compose exec dev vendor/bin/pest

# Run e2e tests (requires dev server running on localhost:8080)
./scripts/run-e2e-tests.sh

# Run CLI delivery script (also triggered hourly by cron)
docker exec simple-newsletter php /app/bin/send-newsletters.php

# Build production image
docker compose -f compose-prod.yaml build

# Deploy production
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

Manual DI via `final class Container` (no framework, no container-interop). Public factory methods: `subscriptions()`, `responder()`. Private factories with singleton caching:
- `$database` as `static ?\PDO`
- `$auth` and `$sender` as `static ?\WeakReference` (allows GC between requests in long-running FrankenPHP)

### Error Handling

- `EndUserException extends \Exception` — thrown in Models and Adapters for user-visible errors (invalid URI, bad email, token failure, feed fetch failure)
- Caught in HTTP entrypoints → rendered as 400 responses
- `FeedImporterLaminas` wraps `HttpClientException|FeedException` into `EndUserException`
- Sentry integration via `SENTRY_DSN` env var (initialized in bootstrap.php)

### Naming

- Classes: PascalCase, descriptive nouns
- Methods: camelCase
- Private factory methods: lowercase snake_case (`emailTemplateFactory()`, `auth()`)
- DAO methods: `find()`, `new()`, `update()`, `activate()`, `deactivate()`
- Database columns: snake_case
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
| `public/index.php` | Landing page with subscription form |
| `public/v1/subscriptions/index.php` | Subscription handler |
| `bin/send-newsletters.php` | Cron delivery entrypoint |
| `libs/Container.php` | Manual DI container wiring |
| `libs/bootstrap.php` | Autoload + Sentry init (prepended via php.ini `auto_prepend_file`) |
| `libs/Models/Subscriptions.php` | Core subscription workflow |
| `libs/Models/Feeds.php` | Feed retrieval + caching (24h TTL) |
| `libs/Models/Newsletter.php` | Email composition + sending orchestration |
| `config/database.php` | Returns SQLite DSN array |
| `migrations/*.sql` | Schema evolution (sequential, SQLite-compatible) |
| `specs/` | Authoritative design specs |

## Runtime/Tooling Preferences

- **Runtime**: PHP 8.3+ via FrankenPHP (Caddy embedded)
- **Package manager**: Composer
- **Dev tooling**: `intelephense` (PHP language server) via npm (in package.json)
- **Database**: SQLite3 via PDO (in-memory for tests)
- **Container**: Docker with multi-stage builds (devcontainer for VS Code)
- **CI**: Dependabot for monthly dependency updates
- **No framework**, no static analysis, no build step for PHP

## Testing & QA

- **Framework**: Pest (PHPUnit-based) with 100+ unit/integration tests; e2e tests with Symfony HTTP Client
- **Run**: `vendor/bin/pest` (unit), `./scripts/run-e2e-tests.sh` (e2e, requires running dev server)
- **Structure**: `tests/` mirrors `libs/`; e2e tests in `tests/E2e/`
- **Config**: `phpunit.xml.dist` excludes `tests/E2e/` from default suite; e2e uses separate testsuite
- **Database**: In-memory SQLite for unit tests; `data/test-e2e.db` for e2e
- **Patterns**: DAOs test in-memory SQLite; Models mock I/O; e2e tests exercise full HTTP stack

## Spec-Driven Development Workflow

1. **Read** the relevant spec in `specs/` before any feature work. Use `specs/README.md` as index.
2. Specs describe intent, not implementation — verify reality in the codebase
3. Implement to spec patterns and data shapes
4. Update specs only when asked
5. When writing specs, do **not** follow TDD — write the spec first and stop
