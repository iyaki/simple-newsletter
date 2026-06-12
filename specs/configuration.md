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
| `SENTRY_DSN` | No | — | Sentry error tracking DSN |
| `SMTP_HOST` | No | — | SMTP server hostname |
| `SMTP_PORT` | No | — | SMTP server port |
| `SMTP_USER` | No | — | SMTP username |
| `SMTP_PASS` | No | — | SMTP password |
| `SMTP_FROM` | No | — | From email address for newsletters |
| `AUTH_KEY` | No | — | HMAC secret key for token generation |

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
- `AUTH_KEY` should be a long random string for HMAC signing.
- SQLite database file should have restricted file permissions (0600).
- `.env` is in `.gitignore` and should never be committed.
