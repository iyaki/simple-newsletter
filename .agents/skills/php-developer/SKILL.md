---
name: php-developer
description: PHP coding conventions and architecture patterns for this project.
---

# PHP Developer Guidelines

## Project Conventions

- **PHP version**: 8.3 (see Dockerfile)
- **Strict types**: `declare(strict_types=1)` in every file.
- **Framework**: None — vanilla PHP with FrankenPHP.
- **Autoload**: PSR-4, namespace `SimpleNewsletter\`, root `libs/`.
- **DI**: Manual dependency injection via `Container` class (no framework container).
- **SQLite** via PDO for persistence.

## Code Style

- PSR-12 coding style.
- PHP 8.x features preferred: readonly classes, named arguments, match expressions.
- Type hints required on all parameters and return types.
- `never` return type for entrypoint scripts (exit handlers).
- Use `final` keyword on every class unless there is an explicit extension contract.

## Architecture Patterns

### Layers

```
public/        → HTTP entrypoints (thin, parse request → call model → respond)
libs/
  Models/      → Domain logic (orchestrates components)
  Components/  → Shared interfaces and value objects
  Adapters/    → Concrete I/O implementations
  Data/        → Data access objects and value objects
  Templates/   → Email and API response templates
config/        → Configuration files
bin/           → CLI entrypoints
```

### Rule of thumb

- **Models** do orchestration. They depend on interfaces, not concretions.
- **Adapters** implement interfaces. They're the only things doing I/O.
- **Data** contains DAOs (data access) and simple value objects (DTOs).
- **Components** define interfaces and shared value types.
- **Templates** are template-rendering logic (email HTML, API responses).

### Error Handling

- User-facing errors: throw `EndUserException` (caught at entrypoint level).
- System errors: throw standard exceptions or let PHP errors surface.
- Never echo/print from domain logic.

## Testing

- PHPUnit for unit tests.
- Tests go in `tests/` mirroring `libs/` structure.
- Use in-memory SQLite for DAO tests.
- Mock external I/O (HTTP, email) in model tests.
