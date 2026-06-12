# Deployment

Status: Implemented

## Overview

### Purpose

Define the deployment architecture, Docker container setup, and scheduled task configuration.

## Deployment

### Docker deployment

The service runs as a FrankenPHP container with Caddy as the web server.

```yaml
# compose-prod.yaml — Production stack
services:
  app:
    build:
      context: .
      target: production
    ports:
      - 80:80
      - 443:443
    volumes:
      - caddy_data:/data
      - caddy_config:/config
    env_file: .env
```

### Build stages

1. **runtime** — Base FrankenPHP image.
2. **dependencies** — Install PHP extensions and Composer.
3. **dev-environment** — Dev tools (xdebug, vim, git).
4. **app-compilation** — Copy source, run `composer install --no-dev`.
5. **production** — Alpine-based, minimal, with Caddy.

### Scheduled task

Newsletter delivery runs via cron on the host:

```cron
15 * * * * docker exec simple-newsletter php /app/bin/send-newsletters.php >> /root/send-newsletters.log
```

This runs every hour at minute 15.

### Updates

```bash
docker compose up --wait --pull always
```

## Infrastructure

- **Web server**: FrankenPHP (embedded Caddy) — auto HTTPS via Let's Encrypt.
- **Database**: SQLite (file-based, no separate DB server).
- **Error tracking**: Sentry (optional, via `SENTRY_DSN`).
- **Analytics**: GoatCounter (privacy-focused, embedded in landing page).

## Security Considerations

- Caddy auto-provisions TLS certificates for production domains.
- No exposed database ports (SQLite is file-based).
- Container runs as non-privileged user in production.
- PHP error display disabled in production (`production.ini`).
