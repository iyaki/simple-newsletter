# Simple Newsletter auth.md

This service has no agent accounts and no OAuth infrastructure. Agents interact
with the public subscription API exactly like any other client; registration is
the subscription flow itself.

## Audience

Agents acting on behalf of a person who wants a web feed (Atom/RSS) delivered to
an email inbox as periodic newsletters.

## Registration / provisioning

`GET|POST /v1/subscriptions/`

Parameters:

- `uri` — feed URI (Atom or RSS) to subscribe to.
- `email` — subscriber email address.
- `return` — optional redirect target after subscribing (HTML clients).
- `redirect` — set to force redirect mode.

Responses negotiate on the `Accept` header: `application/json` returns JSON,
anything else returns HTML.

Double opt-in: a confirmation message is sent to `email` containing a
`GET /v1/subscriptions/confirmation/?uri=…&email=…&token=…` link. The
subscription is active only after that link is followed. Follow it to complete
provisioning.

## Credentials

There are no API keys or bearer tokens. The only credential is the per-
subscription token delivered by email, used in the confirmation link above and
in the corresponding cancellation link
(`GET /v1/subscriptions/cancellation/`). Tokens are single-purpose and scoped to
one (feed, email) pair; store them if the agent needs to cancel later.

## Other discovery

- API specification: `/api-spec.yaml` and `/.well-known/api-catalog/`
- Service description: `/` (send `Accept: text/markdown` for Markdown)
