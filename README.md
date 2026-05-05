# Cataloga

Cataloga is an AI-native, Git/file-backed, domain-agnostic registry platform.

## Cataloga status

Cataloga is now **PHP-only**.

- Primary and only implementation runtime: PHP (`apps/php`).
- Primary local/self-hosted execution path: Docker Compose.
- Canonical registry data source: Git/file-backed content under `registry/`.
- Domain packs remain under `domain-packs/`.
- All writes must go through change sessions (no direct write paths).
- Legacy Node.js/TypeScript implementation has been removed from this repository.
- Managed hosting and Cloudflare Worker demo have been removed from this repository.

## Quick start (mise + Docker Compose)

Prepare and start:

```bash
mise install
mise run verify
```

This runs:

- runtime directory setup (`.cataloga/`)
- local toolchain verification (`php`)
- `docker compose` config validation
- build/start (`docker compose up --build -d`)
- API health check (`/api/entities`)
- container status check

PHP syntax check:

```bash
mise run php-lint
```

Stop:

```bash
mise run down
```

Open:

```text
http://localhost:8080
```

## Repository layout

```text
apps/
  php/
    composer.json
    public/
      index.php
    src/
    templates/
registry/
domain-packs/
docker/
docker-compose.yml
```

## Core behavior

- Git/file-backed registry remains the source of truth.
- Runtime state (for example `.cataloga/`) is derived/non-canonical state.
- Mutations are staged and applied only via change sessions.
- Validation and diff preview happen before commit.
- Git diff/commit integration is provided by the PHP app.

## JSON/API + MCP direction

Current API endpoints are served by the PHP app and share the same mutation engine.
Future MCP support must use or wrap the **same PHP mutation/change-session path** so write semantics remain auditable and consistent.

## CI

Primary CI workflow is the PHP workflow under `.github/workflows/`.

It verifies:

- `composer validate` (`apps/php`)
- `composer install --no-interaction --prefer-dist` (`apps/php`)
- PHP lint (`php -l`) over `apps/php`
- `docker compose config`
- `docker compose build`


## Schema-driven registry forms (Phase 1)

- `/entities/new` now starts with schema/type selection from `registry/schemas/*.yaml` and `domain-packs/*/schemas/*.yaml`.
- Normal mode renders schema-driven fields (string, text, boolean, enum, array<string>, json, entity_ref).
- Advanced mode keeps raw JSON editing (`spec`) plus manual `sourcePath` override.
- `metadata.type` is taken from selected schema; `metadata.id` auto-generates from `type + name` when omitted.
- Domain packs contribute schema choices to the human UI.
- `/relations/new` now uses existing entity selections for `from`/`to`, with advanced fields kept in Advanced mode.
