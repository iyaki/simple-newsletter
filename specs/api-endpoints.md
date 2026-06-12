# API Endpoints

Status: Implemented

## Overview

### Purpose

Define the HTTP API surface for subscription management and publisher integration.

## APIs

### Base URL

`https://simple-newsletter.com/v1`

### Endpoints

#### GET/POST /v1/subscriptions/

Request a new subscription.

| Property | Value |
|----------|-------|
| Description | Starts the subscription process |
| Operation ID | `subscriptionRequest` |
| Content negotiation | JSON (`application/json`) or HTML (`text/html`) based on `Accept` header |
| Redirect | When `redirect=true` and `return` is set, responds 302 to `return` URL |

Query parameters:

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| uri | URL | yes | — | Feed URI to subscribe to |
| email | Email | yes | — | Subscriber email |
| return | URL | no | — | Redirect target URL |
| redirect | Boolean | no | false | Enable redirect response mode |

Response codes:

| Code | Condition | Content |
|------|-----------|---------|
| 200 | Success (HTML/JSON) | `{ title, detail }` object or HTML page |
| 302 | Success with redirect | Redirect to `return` with query params: `title`, `result`, `ok` |
| 400 | Validation error | Error object or HTML page |
| default | Server error | Error object or HTML page |

#### GET /v1/subscriptions/confirmation/

Confirm a subscription via signed link.

| Property | Value |
|----------|-------|
| Description | Confirms a pending subscription |

Query parameters:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| feed_uri | URL | yes | Feed URI |
| email | Email | yes | Subscriber email |
| token | String | yes | Auth token |

Response: HTML page (success or error).

#### GET /v1/subscriptions/cancellation/

Cancel a subscription via signed unsubscribe link.

| Property | Value |
|----------|-------|
| Description | Cancels an active subscription |

Query parameters:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| feed_uri | URL | yes | Feed URI |
| email | Email | yes | Subscriber email |
| token | String | yes | Auth token |

Response: HTML page (success or error).

## JSON response schemas

### JSONResponse

```json
{
  "title": "Operation result title",
  "detail": "Human-readable detail message"
}
```

## Content negotiation

- `Accept: application/json` → JSON response.
- `Accept: text/html` → HTML page response.
- When `redirect=true` and `return` is provided → 302 redirect with query params.
- Default: HTML.

## Versioning

URL-path versioning (`/v1/`). No header-based versioning.

## OpenAPI Spec

The canonical API specification is at `public/api-spec.yaml` (OpenAPI 3.0).
