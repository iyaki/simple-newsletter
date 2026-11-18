# Repository Guidelines

## Project Overview

**Simple Newsletter** converts Atom/RSS feeds into email newsletters via a double-opt-in subscription flow. Built with FrankenPHP (PHP 8.5 + Caddy, single binary), SQLite, and no framework. Followers subscribe to a feed URI, confirm their email, and receive hourly newsletters when new posts are published. No user accounts, no reader UI — just pull-to-email.

**Spec-Driven Development**: every feature has a spec in `specs/`.

## Pointers

- **Coding** — open `CODING_STANDARDS.md` before editing any PHP or test file, or writing a spec.
- **Architecture** — open `ARCHITECTURE.md` before adding code under `libs/`, tracing a request, changing the schema, or adding an endpoint.
- **Operations** — dev/test commands in `README.md` § Development, deploy in `RUNBOOK.md`; local image build only: `devenv shell -- scripts/build-image.sh` (OCI tarball, `infrastructure/image.nix`).

## Environment

Run commands through the nix devenv: `devenv shell -- <cmd>`. devenv.sh is the primary dev environment; devcontainer is a fallback.
