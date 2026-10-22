# Configuration

Status: Implemented

## Overview

### Purpose

Define all configuration parameters, environment variables, and deployment settings.

## Configuration

### Environment variables

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `SERVER_NAME` | No | `http://localhost:8080` | FrankenPHP server name/bind |
| `URI_SELF` | Yes | — | Canonical base URL (confirmation/cancellation/unsubscribe links) |
| `SECRET_KEY` | Yes | — | HMAC secret for token generation (`openssl rand -hex 32`) |
| `SENTRY_DSN` | No | — | Sentry error tracking DSN |
| `SMTP_HOST` | No | `localhost` | SMTP server hostname |
| `SMTP_PORT` | No | `587` | SMTP server port |
| `SMTP_USER` | No | — | SMTP username |
| `SMTP_PASSWORD` | No | — | SMTP password |
| `SMTP_ENCRYPTION` | No | `tls` (STARTTLS) | tls/ssl |
| `SMTP_ALLOW_SELF_SIGNED` | No | disabled | Accept self-signed TLS certs (strict opt-in) |
| `EMAIL_FROM` | No | `noreply@example.com` | From email address for newsletters |
| `EMAIL_REPLY_TO` | No | `noreply@example.com` | Reply-To address |

### Configuration files

| File | Purpose |
|------|---------|
| `.env` | Environment variables (local, not committed) |
| `config/database.php` | SQLite DSN configuration |
| `.php/php.ini` | Base PHP settings |
| `.php/development.ini` | Dev-specific PHP overrides (xdebug, error display) |
| `.php/production.ini` | Production PHP overrides |
| `.caddy/Caddyfile-prod` | Production Caddy reverse proxy config |

### Database path

The SQLite database lives at `data/database.sqlite3`, determined by `config/database.php`.

### Docker compose

- `compose.yaml` — Development stack (bind mounts, xdebug, dev PHP settings).
- `compose-prod.yaml` — Production stack (compiled app, production PHP settings, Caddy).
- `Dockerfile` — Multi-stage build (dependencies → dev → compilation → production).

## Security Considerations

- SMTP credentials are read from environment variables, never hardcoded.
- `SECRET_KEY` should be a long random string for HMAC signing.
- SQLite database file should have restricted file permissions (0600).
- `.env` is in `.gitignore` and should never be committed.
