# Spec Name

Status: Proposed | Implemented

## Overview

### Purpose

- Clearly articulate the problem being solved and the rationale for the feature.
- Jobs to be done and user needs addressed.

### Goals

- Specific, measurable outcomes the spec aims to achieve.

### Non-Goals

- Clarify what is out of scope to prevent scope creep.

### Scope

- Define the boundaries of the feature, including what is included and excluded.

## Architecture

### Module/package layout (tree format)

- High-level organization of code components.

### Component diagram (ASCII)

- Visual representation of how components interact.

### Data flow summary

- Overview of how data moves through the system for key operations.

## Data model

### Core Entities

- Define primary entities with types and fields
- Include code snippets in the target language, only as reference. The only valid source of truth are the written definition and the source code itself, these code snippets are just to clarify the written definition
- Explain relationships between entities

### Relationships

- Explain how entities relate to others features/modules/contexts.

### Persistence Notes

- Schema definitions (as a table with SQL types)
- Indexing and constraints

## Workflows

- Step-by-step flows for critical operations
- Include both happy path and error/merge cases

## APIs

- Base paths
- Endpoints (method, path, purpose)
- Auth requirements
- Request/response payloads

## Client SDK Design

- Initialization patterns
- Example usage
- Behavior expectations (batching, retry, persistence, etc)

## Configuration

- Environment variables, extra configurations and defaults

## Permissions

- Roles and access matrix

## Security Considerations

- Sensitive data handling
- Validation rules
- Key management

## Dependencies

- Library list with rationale where needed

## Open Questions / Risks

## Verifications

Section with 3-5 objective checks.  
Examples:

- Tests pass for capture + identify flows.
- Automated Unit tests
- Automated e2e tests with Playwright
- API returns 401 without key.
- Schema migration applies cleanly on empty DB.

## Appendices

- Compatibility notes
- Future considerations
