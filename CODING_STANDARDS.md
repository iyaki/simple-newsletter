# Coding Standards

Rules for writing PHP, tests, and specs in Simple Newsletter. Architecture, commands, and environment gotchas live in `AGENTS.md`.

## PHP Mandatory Rules

- `declare(strict_types=1)` in **every** file
- PSR-12 coding style
- `final readonly class` for DTOs, models, and adapters where possible
- Type hints required on all parameters and return types
- `never` return type on entrypoint exit handlers
- PSR-4 autoload: namespace `SimpleNewsletter\`, root `libs/`
- `\` prefix on PHP built-in calls (`\getenv`, `\password_hash`)
- Private constructors + `static` named constructors (`fromString`, `fromEndUserException`) on Template classes
- DAOs use private static factory methods to map DB rows → DTOs

## Dependency Injection

Manual DI via `final class Container` (no framework, no container-interop). Singletons: `$database` as `static ?\PDO`; `$auth` and `$sender` as `static ?\WeakReference` — allows GC between requests in long-running FrankenPHP.

## Error Handling

- `EndUserException extends \Exception` — thrown in Models and Adapters for user-visible errors (invalid URI, bad email, token failure, feed fetch failure); caught in HTTP entrypoints and rendered as 400 responses
- `FeedImporterLaminas` wraps `HttpClientException|FeedException` into `EndUserException`

## Naming

- DAO methods: `find()`, `new()`, `update()`, `activate()`, `delete()`
- SQL: heredoc syntax

## Testing

- **Framework**: Pest (PHPUnit-based); e2e tests with Symfony HTTP Client
- **Structure**: `tests/` mirrors `libs/`; e2e tests in `tests/E2e/` (own suite, not in the default run)
- **Database**: In-memory SQLite for unit/integration tests; `data/test-e2e.db` for e2e
- **Patterns**: Models mock I/O; e2e tests exercise the full HTTP stack

## Spec-Driven Development Workflow

1. Read the feature's spec before implementing it; specs describe intent, not implementation — verify reality in the codebase
2. Update specs only when asked
3. When writing specs, do **not** follow TDD — write the spec first and stop
