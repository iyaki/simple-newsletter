# Troubleshooting Guide

Common failure modes and solutions for Simple Newsletter.

## SMTP Issues

### Self-signed certificate errors
**Symptom:** Connection fails with certificate validation error  
**Solution:** Set `SMTP_ALLOW_SELF_SIGNED=true` in `.env`

### Connection timeouts
**Symptom:** Timeout when connecting to SMTP server  
**Solution:** Verify `SMTP_HOST` and `PORT` in `.env` are correct and reachable from the container

### Authentication failures
**Symptom:** SMTP authentication rejected  
**Solution:** Check `SMTP_USER` and `SMTP_PASSWORD` in `.env` match your SMTP provider credentials

### Encryption mismatch
**Symptom:** Connection fails during TLS negotiation  
**Solution:** Set `SMTP_ENCRYPTION=tls` or `ssl` in `.env` based on your provider's requirements

## Subscription Flow Issues

### Confirmation emails not sending
**Symptom:** Subscription succeeds but no email arrives  
**Solution:** 
- Verify `EMAIL_FROM` and `EMAIL_REPLY_TO` are set in `.env`
- Check SMTP configuration (see SMTP Issues above)
- Review logs: `docker compose logs -f`

### Token validation failures
**Symptom:** Confirmation link returns "invalid token" error  
**Solution:** Verify `SECRET_KEY` is set in `.env` and has remained consistent since subscription

### "Class Container not found"
**Symptom:** Fatal error: Class "SimpleNewsletter\Container" not found  
**Solution:** Ensure `libs/bootstrap.php` is auto-prepended via FrankenPHP's `php.ini` configuration. In the Caddyfile, set:
```
php_ini auto_prepend_file /app/libs/bootstrap.php
```

## Feed Import Issues

### Invalid feed URI
**Symptom:** "Invalid feed URI" or "Feed not found" error  
**Solution:** Validate the feed with a feed validator (e.g., validator.w3.org/feed/) before subscribing

### Feed fetch failures
**Symptom:** Network error or SSL certificate error when fetching feed  
**Solution:** 
- Verify the feed URL is accessible from the container
- Check SSL certificates are valid
- Some feeds may require custom User-Agent headers

## Database Issues

### SQLite lock errors
**Symptom:** "database is locked" error  
**Solution:** Check file permissions on `data/` directory. Ensure the container user can read/write:
```bash
chmod 755 data/
chown -R www-data:www-data data/
```

### Schema migration needed
**Symptom:** Column not found or table doesn't exist errors  
**Solution:** Run migrations in sequence from `migrations/` directory. Check current schema matches expected state.

## Development Environment

### Docker container won't start
**Symptom:** Container exits immediately or fails to start  
**Solution:** Check logs: `docker compose logs`. Common causes:
- Port already in use (change `80:80` mapping in `compose.yaml`)
- Missing `.env` file or required variables
- Volume mount permissions

### Tests failing
**Symptom:** Unit or integration tests fail unexpectedly  
**Solution:** Run tests in clean environment:
```bash
docker compose exec dev vendor/bin/pest
```
If e2e tests fail, ensure dev server is running on `localhost:8080`:
```bash
./scripts/run-e2e-tests.sh
```

### Port conflicts
**Symptom:** Address already in use error  
**Solution:** Change the host port mapping in `compose.yaml` from `80:80` to `8080:80` or another available port

### FrankenPHP bootstrap issues
**Symptom:** HTTP endpoints fail but CLI works  
**Solution:** FrankenPHP's HTTP SAPI may handle `auto_prepend_file` differently than CLI. Verify the Caddyfile includes the `php_ini` directive for bootstrap.php

## Production Deployment

### Container won't pull on server
**Symptom:** `docker compose up` fails with pull error  
**Solution:** Force pull latest image: `docker compose -f compose-prod.yaml up --pull always`

### Cron job not triggering deliveries
**Symptom:** No newsletters sent at scheduled times  
**Solution:** Verify cron is running and logs show execution:
```bash
grep send-newsletters /var/log/cron
```
Test manually: `docker exec simple-newsletter php /app/bin/send-newsletters.php`

### Environment variables not loaded
**Symptom:** App uses defaults instead of `.env` values  
**Solution:** Ensure `.env` file is in the same directory as `compose-prod.yaml` and variables are exported correctly