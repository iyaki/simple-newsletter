# Deployment Runbook

Step-by-step deployment and maintenance procedures for Simple Newsletter.

## Initial Setup

1. **Copy files to server**
   ```bash
   scp compose-prod.yaml .env.example user@server:/path/to/deploy/
   ```

2. **Generate SECRET_KEY**
   ```bash
   openssl rand -hex 32
   ```
   Save this value in `.env`

3. **Create data directory**
   ```bash
   mkdir -p data
   chmod 755 data
   ```

4. **Configure environment**
   Create `.env` with:
   ```bash
   SECRET_KEY=<generated-above>
   SMTP_HOST=<your-smtp-host>
   SMTP_PORT=587
   SMTP_USER=<username>
   SMTP_PASSWORD=<password>
   SMTP_ENCRYPTION=tls
   SMTP_ALLOW_SELF_SIGNED=false
   EMAIL_FROM=newsletter@example.com
   EMAIL_REPLY_TO=noreply@example.com
   URI_SELF=https://your-domain.com
   SERVER_NAME=your-domain.com
   ```

5. **Verify Caddyfile**
   Ensure `Caddyfile` has:
   ```
   frankenphp {
       php_ini auto_prepend_file /app/libs/bootstrap.php
   }
   ```

## Deploy

```bash
docker compose -f compose-prod.yaml up --wait --pull always
```

## Verify

```bash
# Check container status
docker compose ps

# Check HTTP endpoint
curl -I https://your-domain.com

# Check logs
docker compose logs -f
```

Expected: HTTP 200, no error logs.

## Cron Setup

Newsletter delivery runs hourly. Add to server crontab (`crontab -e`):

```bash
15 * * * * docker exec simple-newsletter php /app/bin/send-newsletters.php >> /var/log/send-newsletters.log 2>&1
```

Verify cron job:
```bash
grep send-newsletters /var/log/cron
```

## Maintenance

### View logs

```bash
# Follow all logs
docker compose logs -f

# Follow specific service
docker compose logs -f simple-newsletter

# Last 100 lines
docker compose logs --tail=100
```

### Manual newsletter send

Trigger delivery outside of cron:
```bash
docker exec simple-newsletter php /app/bin/send-newsletters.php
```

### Check database

```bash
# Enter container shell
docker exec -it simple-newsletter sh

# Query SQLite
sqlite3 /app/data/database.sqlite3

# Example queries
SELECT count(*) FROM feeds;
SELECT count(*) FROM subscriptions WHERE confirmed = 1;
```

### Database backup

```bash
# Simple copy (while container running - SQLite handles this)
docker exec simple-newsletter cp /app/data/database.sqlite3 /backup/db-$(date +%F).sqlite3

# Or use SQLite backup API for safe online backup
docker exec simple-newsletter sqlite3 /app/data/database.sqlite3 ".backup '/backup/db-$(date +%F).sqlite3'"
```

### Restore database

```bash
# Stop container
docker compose -f compose-prod.yaml stop

# Restore backup
docker exec simple-newsletter cp /backup/db-YYYY-MM-DD.sqlite3 /app/data/database.sqlite3

# Restart container
docker compose -f compose-prod.yaml start
```

### Update dependencies

```bash
# Update composer dependencies (in dev environment)
docker compose exec dev composer update

# Rebuild production image
docker compose -f compose-prod.yaml build

# Redeploy
docker compose -f compose-prod.yaml up --wait --pull always
```

### Update application code

```bash
# Pull latest changes (if using git on server)
git pull origin main

# Rebuild and redeploy
docker compose -f compose-prod.yaml up --wait --pull always
```

## Troubleshooting Deployment

### Container exits immediately

```bash
# Check logs
docker compose logs

# Common causes:
# - Missing .env file
# - Invalid SMTP credentials
# - Port already in use
```

### SMTP not working

1. Verify `.env` has correct SMTP credentials
2. Test connection from server:
   ```bash
   telnet <SMTP_HOST> <SMTP_PORT>
   ```
3. Check `SMTP_ENCRYPTION` matches provider requirements (tls/ssl)
4. For self-signed certs: `SMTP_ALLOW_SELF_SIGNED=true`

### Database errors

```bash
# Check file permissions
ls -la data/

# Fix if needed
chmod 644 data/database.sqlite3
chmod 755 data/
```

### Failed health checks

```bash
# Check container logs
docker compose logs simple-newsletter

# Manual health check
curl -f http://localhost:80/health
```

## Rollback

If deployment fails:

```bash
# Stop and remove
docker compose -f compose-prod.yaml down

# Restore previous backup
docker exec simple-newsletter cp /backup/db-YYYY-MM-DD.sqlite3 /app/data/database.sqlite3

# Redeploy previous version
git checkout <previous-commit>
docker compose -f compose-prod.yaml build
docker compose -f compose-prod.yaml up --wait --pull always
```

## Environment Variables Reference

| Variable | Required | Description |
|----------|----------|-------------|
| `SECRET_KEY` | Yes | HMAC secret for tokens (`openssl rand -hex 32`) |
| `SMTP_HOST` | Yes | SMTP relay hostname |
| `SMTP_PORT` | Yes | SMTP port (typically 587 for TLS) |
| `SMTP_USER` | Yes | SMTP username |
| `SMTP_PASSWORD` | Yes | SMTP password |
| `SMTP_ENCRYPTION` | No | `tls` (default) or `ssl` |
| `SMTP_ALLOW_SELF_SIGNED` | No | Accept self-signed certs (`true`/`false`) |
| `EMAIL_FROM` | Yes | From address for emails |
| `EMAIL_REPLY_TO` | Yes | Reply-to address |
| `URI_SELF` | Yes | Public base URL (for links) |
| `SERVER_NAME` | Yes | Caddy server name (domain) |
| `SENTRY_DSN` | No | Sentry error tracking DSN |
