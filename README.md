# Simple Newsletter

A service that converts Atom and RSS feeds into email newsletters. Readers subscribe to any feed via email, making feed content accessible without an RSS reader.

Built with [FrankenPHP](https://frankenphp.dev/), PHP 8.3, SQLite.

## Features

- **Double opt-in subscriptions** - Users confirm via email before receiving newsletters
- **Automatic delivery** - Scheduled cron job fetches and sends new posts automatically
- **Multiple response formats** - JSON for APIs, HTML for browser forms, redirects for UX
- **Content negotiation** - Automatic `Accept` header handling
- **Self-hosted** - Single container deployment, no external dependencies

## Spec-Driven Development

This repository follows a **Spec-Driven Development (SDD)** workflow designed for AI agent collaboration.

### Workflow

1. **Read the specs**: Start with [`specs/README.md`](specs/README.md) to understand the system.
2. **Plan**: Use `ralph plan <scope>` or review [`IMPLEMENTATION_PLAN.md`](IMPLEMENTATION_PLAN.md).
3. **Build**: Implement against the specs using TDD practices.
4. **Verify**: Tests confirm spec compliance.

### Key files

| File | Purpose |
|------|---------|
| [`specs/`](specs/) | Technical specifications (source of truth for intent) |
| [`AGENTS.md`](AGENTS.md) | AI agent guidelines and conventions |
| [`IMPLEMENTATION_PLAN.md`](IMPLEMENTATION_PLAN.md) | Implementation plan and status |
| [`.agents/skills/`](.agents/skills/) | Reusable skill definitions for AI coding agents |
| [`.opencode/`](.opencode/) | OpenCode AI tool configuration |

## Deploy

See [`RUNBOOK.md`](RUNBOOK.md) for complete deployment and maintenance procedures.

### Quick Deploy

 Copy `compose-prod.yaml` and `.env` to the server and create folder `data/`.

Service starts and updates with:

```bash
docker compose -f compose-prod.yaml up --wait --pull always
```

Cron for scheduled newsletter delivery:

```bash
15 * * * * docker exec simple-newsletter php /app/bin/send-newsletters.php >> /root/send-newsletters.log
```

### Post-Deploy Verification

```bash
curl -I https://your-domain.com
docker compose logs -f
```

## Troubleshooting

See [`TROUBLESHOOTING.md`](TROUBLESHOOTING.md) for common issues and solutions:
- SMTP connection problems
- Confirmation email failures
- Database lock errors
- Docker container issues

## Development

```bash
# Start dev environment
docker compose up

# Run tests
docker compose exec dev vendor/bin/pest

# Send newsletters manually
docker compose exec dev php /app/bin/send-newsletters.php


## Cleanup

```bash
docker system prune --all --force --volumes
docker image prune -f
```
