# Implementation Plan

**Status:** Core service is implemented and deployed.
**Last Updated:** 2026-06-12
**Primary Specs:** `specs/core-architecture.md`, `specs/subscription-flow.md`, `specs/feed-importer.md`, `specs/newsletter-delivery.md`, `specs/api-endpoints.md`, `specs/database-schema.md`, `specs/configuration.md`, `specs/deployment.md`

## Quick Reference

| System/Subsystem | Specs | Modules | Current State |
|---|---|---|---|
| Landing page | `specs/vision-goals.md` | `public/index.php` | Implemented |
| Subscription flow | `specs/subscription-flow.md` | `public/v1/subscriptions/index.php`, `libs/Models/Subscriptions.php`, `libs/Data/SubscriptionsDAO.php` | Implemented |
| Subscription confirmation | `specs/subscription-flow.md` | `public/v1/subscriptions/confirmation/index.php`, `libs/Models/Subscriptions.php` | Implemented |
| Subscription cancellation | `specs/subscription-flow.md` | `public/v1/subscriptions/cancellation/index.php`, `libs/Models/Subscriptions.php` | Implemented |
| Feed importing | `specs/feed-importer.md` | `libs/Models/Feeds.php`, `libs/Adapters/FeedImporterLaminas.php`, `libs/Data/FeedsDAO.php` | Implemented |
| Newsletter delivery | `specs/newsletter-delivery.md` | `libs/Models/Newsletter.php`, `libs/Adapters/SenderPHPMailer.php`, `bin/send-newsletters.php` | Implemented |
| Auth token generation | `specs/subscription-flow.md` | `libs/Components/Auth.php` | Implemented |
| Email templates | `specs/newsletter-delivery.md` | `libs/Components/EmailTemplateFactory.php`, `libs/Templates/Email/` | Implemented |
| HTTP response handling | `specs/api-endpoints.md` | `libs/Adapters/ResponderHttp.php` | Implemented |
| Dependency injection | `specs/core-architecture.md` | `libs/Container.php` | Implemented |

## Phased Plan

### Phase 1: Foundation and Core Setup

**Goal:** Basic project scaffolding, container setup, database schema.
**Status:** Complete

#### 1.1 Project scaffold

- [x] Dockerfile with FrankenPHP.
- [x] Docker compose for dev and production.
- [x] Composer setup with autoload.
- [x] SQLite database setup.
- [x] PHP configuration (dev/production).

#### 1.2 Database schema

- [x] `feeds` table.
- [x] `subscriptions` table.
- [x] Indexes.

### Phase 2: Feed Importing

**Goal:** Fetch and parse Atom/RSS feeds.
**Status:** Complete

#### 2.1 Feed importing

- [x] `FeedImporter` interface.
- [x] `FeedImporterLaminas` adapter.
- [x] `FeedsDAO` for persistence.
- [x] Feed caching (24h TTL).

### Phase 3: Subscription Management

**Goal:** Allow users to subscribe, confirm, and unsubscribe from feeds.
**Status:** Complete

#### 3.1 Subscription request

- [x] Entrypoint with validation.
- [x] Feed retrieval and caching.
- [x] Token generation via `Auth`.
- [x] Confirmation email sending.

#### 3.2 Confirmation flow

- [x] Token verification.
- [x] Status update (confirmed=1).
- [x] Success/error page rendering.

#### 3.3 Cancellation flow

- [x] Token verification.
- [x] Subscription removal.
- [x] Success/error page rendering.

### Phase 4: Newsletter Delivery

**Goal:** Periodic delivery of new feed posts to confirmed subscribers.
**Status:** Complete

#### 4.1 Newsletter composition

- [x] `EmailTemplateFactory` for HTML templates.
- [x] `SenderPHPMailer` adapter.
- [x] Per-subscriber individual emails.

#### 4.2 Scheduled delivery

- [x] `bin/send-newsletters.php` CLI entrypoint.
- [x] Feed re-fetch for new posts.
- [x] Last-sent-post tracking on feeds.
- [x] Cron integration.

### Phase 5: API and Integration

**Goal:** Expose subscription functionality for non-interactive clients.
**Status:** Complete

#### 5.1 Content negotiation

- [x] JSON vs HTML response selection.
- [x] Redirect-based flow for publishers.

#### 5.2 OpenAPI spec

- [x] `public/api-spec.yaml` definition.

### Phase 6: SDD/AI-First Workflow

**Goal:** Adapt repository for spec-driven development with AI agent tooling.
**Status:** Complete

#### 6.1 Agent skills

- [x] `.agents/` directory with skill definitions.
- [x] Spec creator skill with template.
- [x] TDD skill for PHP.

#### 6.2 Specifications

- [x] `specs/README.md` index.
- [x] Core architecture spec.
- [x] Feature specs for all subsystems.
- [x] Configuration and deployment specs.

#### 6.3 OpenCode integration

- [x] `.opencode/commands/spec-status.md`.
- [x] `.opencode/plans/` directory.
- [x] `.opencode/skills` symlink.

#### 6.4 Documentation

- [x] `AGENTS.md` with guidelines.
- [x] `IMPLEMENTATION_PLAN.md`.
- [x] Updated `README.md` with SDD workflow.
- [x] Updated `.gitignore`.

## Future Phases

### Phase 7: Testing Infrastructure

- [ ] PHPUnit configuration (phpunit.xml.dist).
- [ ] Unit tests for Models layer.
- [ ] Unit tests for Data layer (DAOs).
- [ ] Unit tests for Adapters.
- [ ] Unit tests for Components.
- [ ] E2E tests for subscription flow.

### Phase 8: Quality Gates

- [ ] PHPStan or Psalm static analysis.
- [ ] PHP CodeSniffer (PHPCS) for style.
- [ ] GitHub Actions CI pipeline.
- [ ] Security scanning.

### Phase 9: Enhancements

- [ ] Rate limiting on subscription endpoints.
- [ ] Email address normalization.
- [ ] Feed health monitoring.
- [ ] Subscriber count dashboard.
- [ ] Multi-language email templates.
