# Cataloga

Cataloga is an AI-native, Git/file-backed, domain-agnostic registry platform.

## Cataloga v2 status

Cataloga v2 is now **PHP-only**.

- Primary and only implementation runtime: PHP (`apps/php`).
- Primary local/self-hosted execution path: Docker Compose.
- Canonical registry data source: Git/file-backed content under `registry/`.
- Domain packs remain part of v2 under `domain-packs/`.
- All writes must go through change sessions (no direct write paths).
- Node.js/TypeScript v1 implementation has been removed from this repository.
- Managed hosting and Cloudflare Worker demo have been removed from this repository.

## Quick start (Docker Compose)

```bash
docker compose up --build
```

Open:

```text
http://localhost:8080
```

## Repository layout (v2)

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

## CI (v2 mainline)

Primary CI workflow: `.github/workflows/cataloga-v2-php.yml`.

It verifies:

- `composer validate` (`apps/php`)
- `composer install --no-interaction --prefer-dist` (`apps/php`)
- PHP lint (`php -l`) over `apps/php`
- `docker compose config`
- `docker compose build`
