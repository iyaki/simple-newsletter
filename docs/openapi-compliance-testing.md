# OpenAPI Compliance Testing Guide

## Overview

This project now includes OpenAPI compliance testing infrastructure to validate that HTTP responses conform to the OpenAPI specifications defined in:
- `specs/api-internal.yaml` - Internal API endpoints
- `public/api-spec.yaml` - Public API endpoints

## What's Implemented

### 1. Compliance Test Suite (`tests/E2e/OpenApiComplianceTest.php`)

9 comprehensive tests covering:
- ✅ Error response structure validation (400 responses)
- ✅ Content negotiation (HTML vs JSON based on Accept header)
- ✅ JSONResponse schema compliance (`title`, `detail` fields)
- ✅ Required header validation (`X-Robots-Tag`)
- ✅ Confirmation endpoint response validation
- ✅ Cancellation endpoint response validation

## Running the Tests

```bash
# 1. Start the dev server
php -S localhost:8080 -t public &

# 2. Initialize test database and run tests
./scripts/run-e2e-tests.sh

# Or run just the OpenAPI compliance tests
vendor/bin/pest tests/E2e/OpenApiComplianceTest.php
```

## Test Patterns

### Testing Response Structure
```php
it('returns valid error structure', function () {
    $response = self::get('/v1/subscriptions/', [
        'uri' => 'invalid-uri',
        'email' => 'test@example.com',
    ]);

    expect($response->getStatusCode())->toBe(400);
    
    $contentType = $response->getHeaders()['content-type'][0];
    
    if (str_contains($contentType, 'application/json')) {
        $body = \json_decode($response->getContent(false), true);
        expect($body)->toHaveKey('title'); // Per OpenAPI JSONResponse schema
    }
});
```

### Testing Headers
```php
it('includes X-Robots-Tag header', function () {
    $response = self::get('/v1/subscriptions/confirmation/', [
        'uri' => 'https://example.com/feed.xml',
        'email' => 'test@example.com',
        'token' => $token,
    ]);

    $headers = $response->getHeaders();
    expect($headers)->toHaveKey('x-robots-tag');
    expect($headers['x-robots-tag'][0])->toContain('noindex');
});
```

## Known Issues & Notes

### Symfony HttpClient 7.x Behavior

Symfony HttpClient 7.x throws `ClientException` from `getHeaders()`/`getContent()` for 4xx/5xx status codes. Pass `false` (`getHeaders(false)`, `getContent(false)`) to read error responses without throwing; `getStatusCode()` never throws on HTTP error statuses.

### Application Behavior vs OpenAPI Spec

Some endpoints have behavior that differs from the OpenAPI specification:
- **Unknown routes** return 200 (landing page) instead of 404
- **Valid subscriptions** may fail due to feed fetching errors (404 from feed URLs)
- **Token validation** may have edge cases

These are application issues to fix, not test infrastructure problems.

## Next Steps for Full Compliance

1. **Fix application to match spec**:
   - Make unknown routes return 404
   - Ensure all error responses conform to JSONResponse schema
   - Add `X-Robots-Tag` header to all confirmation/cancellation responses

2. **Expand test coverage**:
   - Add tests for rate limiting responses
   - Test redirect behavior (302 responses)
   - Validate query parameter schemas

3. **Continuous validation**:
   - Add OpenAPI compliance checks to CI pipeline
   - Generate OpenAPI client code from spec for type safety
   - Use contract testing to ensure frontend/backend alignment

## Component Locations

| File | Purpose |
|------|---------|
| `tests/E2e/OpenApiComplianceTest.php` | Compliance test suite |
| `tests/E2e/bootstrap.php` | Test environment setup |
| `specs/api-internal.yaml` | OpenAPI specification |
| `phpunit.xml.dist` | Test configuration (e2e testsuite) |