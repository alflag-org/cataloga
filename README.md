# Cataloga

Cataloga is an AI-native, Git/file-backed, domain-agnostic registry platform.

Cataloga v2 uses audited change sessions so humans and AI agents can explore, validate, edit, diff, and commit registry data through one mutation flow.

## Current v2 implementation

- Self-hosted PHP web app (`apps/php`) as the primary runtime.
- Docker Compose execution for local/self-hosted operation.
- Canonical file-backed registry under `registry/` (`yaml`/`json`).
- Change-session based mutations (`upsert_entity`, `delete_entity`).
- Validation before commit.
- Git diff and Git commit integration via allowlisted commands.
- Minimal JSON API for agent workflows (future MCP-ready).

Managed hosting control plane and multi-tenant SaaS abstractions are out of scope for v2.

## Quick start (Docker)

```bash
docker compose up --build
```

Open:

```text
http://localhost:8080
```

## Repository layout (v2 runtime)

```text
apps/
  php/
    composer.json
    public/
      index.php
    src/
      Http/
      Registry/
      Mutation/
      Validation/
      Git/
      Audit/
      View/
    templates/
registry/
  schemas/
  entities/
  relations/
  views/
  policies/
  evidence/
domain-packs/
  example/
    pack.yaml
    schemas/
    views/
    policies/
docker/
  php/
    Dockerfile
docker-compose.yml
```

## UI pages

- `/` dashboard
- `/entities` entity list
- `/entities/{id}` entity detail
- `/entities/new` create form
- `/entities/{id}/edit` edit form
- `/changes` change-session list
- `/changes/{id}` change-session page (validation/diff/commit/abort)
- `/validation` registry validation
- `/git/diff` git diff (`registry`)

## JSON API

### Explore (read-only)

- `GET /api/entities`
- `GET /api/entities/{id}`
- `GET /api/relations` (relation records with stable `id/type/path` metadata)
- `GET /api/schemas` (schema records with stable `id/type/path` metadata)
- `GET /api/entities/{id}/neighbors` (entity graph neighbors + relation edges + counts/errors)
- `GET /api/search?q=` (registry-wide search with stable metadata and counts)
- `GET /api/changes/{id}/summary` (change-session summary optimized for agents)

### Mutate (change-session only)

- `POST /api/changes`
- `GET /api/changes/{id}`
- `POST /api/changes/{id}/operations`
- `POST /api/changes/{id}/validate`
- `GET /api/changes/{id}/diff`
- `POST /api/changes/{id}/commit`
- `POST /api/changes/{id}/abort`

All writes must go through change sessions; direct entity write endpoints are intentionally omitted.
Explore endpoints are read-only and reuse existing repositories/services so mutation paths stay unchanged.

## Change session flow

1. Start change session (`POST /api/changes` or UI form).
2. Add mutation operations (`upsert_entity` / `delete_entity`).
3. Validate pending state.
4. Review diff.
5. Commit to registry files.
6. Optionally create a Git commit.
7. Audit log written to `.cataloga/audit.log` (runtime/derived state, non-canonical and non-Git-managed by default).


## Runtime state policy (`.cataloga`)

- `.cataloga/` is runtime/derived state and is **not** part of canonical registry truth.
- Canonical, reviewable data lives under `registry/` and is the only default Git staging target in the PHP runtime.
- If long-term audit retention is required, design an explicit canonical path such as `registry/audit/` with schema and review rules; otherwise keep audit artifacts under `.cataloga/` and out of Git.
- If `.cataloga/` files were previously tracked, untrack them with `git rm -r --cached .cataloga` and commit that removal while keeping local runtime files.

## Known limitations

- Authentication/RBAC is not implemented yet.
- CSRF protection is UI-only; API auth is not implemented.
- Relation mutation operations are not implemented yet (entity mutations only).
- Diff preview is file-content based and minimal.
- Existing TypeScript v1/vlegacy modules remain in-repo for migration safety but are not the primary v2 runtime.

## Next steps

1. Add authentication and RBAC for UI/API/MCP tool boundaries.
2. Implement relation mutation operations and richer validation rules.
3. Add semantic diff rendering and review workflows.
4. Implement MCP server using the existing mutation engine (`apps/mcp/README.md`).


## CI (v2 mainline)

Primary v2 CI check is GitHub Actions workflow `cataloga-v2-php` (`.github/workflows/cataloga-v2-php.yml`) and runs on pull requests plus pushes to `master`.

It verifies:

- `composer validate` in `apps/php`
- `composer install --no-interaction --prefer-dist` in `apps/php`
- `php -l` lint over `apps/php/**/*.php`
- `docker compose config` at repo root
- `docker compose build` at repo root

Legacy demo deployment workflow remains for demo deployment purposes and is not part of v2 mainline quality checks.
