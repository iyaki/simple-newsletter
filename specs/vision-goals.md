# Vision and Goals

Status: Implemented

## Purpose

Simple Newsletter bridges the gap between RSS/Atom feeds and email newsletters. It lets readers subscribe to any feed via email, making feed content accessible without an RSS reader.

## Goals

1. **Zero-friction subscriptions**: Enter a feed URI and email → receive a confirmation → get newsletters.
2. **Automated feed polling**: Scheduled delivery of new feed posts as email newsletters.
3. **Publisher integration**: Allow publishers to embed subscription forms via HTTP API.
4. **Free and simple**: No accounts, no dashboards, no complexity.

## Non-Goals

- Feed reader replacement: no archive, no read/unread tracking.
- Multi-format output: only email delivery.
- Real-time delivery: newsletters are sent on a schedule (daily granularity).
- User accounts or authentication system.

## Scope

### In scope

- Atom 1.0 and RSS 2.0 feed parsing.
- Email confirmation workflow (double opt-in).
- Scheduled newsletter delivery (daily digests of new posts).
- HTTP API for subscription management.
- Publisher integration (HTML forms, redirect-based flow).

### Out of scope

- Proprietary feed formats.
- Push notifications.
- Web dashboard for managing subscriptions.
- Multi-tenant or organizational features.
