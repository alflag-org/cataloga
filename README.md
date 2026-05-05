# Cataloga

Cataloga is a simple registry for resources and optional hard dependencies.

It helps teams record infrastructure and service resources, connect them with dependencies, review draft changes, and save updates safely.

- Humans use the Web UI.
- Automation uses the HTTP API.
- Type packs add resource types, dependency types, form fields, and validation rules.

## Product model

- `Resource`: a registered catalog item (service, host, VM, VLAN, DNS record, database, repository, cloud account, etc.)
- `Dependency`: a directed relationship between resources (`runs_on`, `uses`, `belongs_to`, `resolves_to`, etc.)
- `Tag`: AWS-style key-value metadata under `metadata.tags` (for environment/owner/site/zone/lifecycle and operational notes)
- `Type pack`: installable extension that provides types, schema metadata, and validation rules
- `Draft change`: staged edits reviewed and validated before save

### Metadata rules

- Tags are key-value only for new writes (`metadata.tags` is a map, not a string list).
- `environment`, `owner`, `site`, `zone`, `visibility`, `lifecycle` and similar cross-cutting metadata are tags.
- Memo-like information uses tags such as `note`, `todo`, `risk` (no first-class `spec.memo`).
- Reserved tag prefixes: `cataloga:`.
- Do not store secrets/sensitive data in tags.

Workspace tag vocabulary is configured in `registry/settings.yaml`.

Management-tag focus can be configured per resource type in `resource_type_profiles`, with fallback to type-pack recommendations and workspace defaults.

### Dependency storage model

- Canonical dependency source is `Resource.dependencies` in `registry/resources/*`.
- Dependencies are optional for normal resource validity.
- Derived dependencies are read-only relation indexes generated from resource dependencies.
- Explicit relations in `registry/relations/*` are for advanced, legacy, or imported cases.
- The dependency list UI is a unified view of `derived dependencies + explicit relations`.
- Tag-based associations remain soft metadata (search/filter/grouping) and are not auto-converted to graph edges.

## Quick start

Prepare and start:

```bash
mise install
mise run verify
```

Open:

```text
http://localhost:8080
```

Stop:

```bash
mise run down
```

## Repository layout

```text
apps/
  php/
    public/
    src/
    templates/
registry/
  resources/
  settings.yaml
domain-packs/
docs/
```

## Core behavior

- Registry files under `registry/` are canonical source of truth.
- Web UI and HTTP API use the same draft-change workflow for writes.
- Save is blocked when validation errors exist.
- Save applies changes to local registry files.
- Git is not part of the core save flow. Optional integrations (Git sync/history, SQLite, MySQL, remote registry backends) can be added later.

## API summary

Read:

- `GET /api/resources`
- `GET /api/resources?type={type}`
- `GET /api/resources/{id}`
- `GET /api/dependencies`
- `GET /api/graph?resource={id}`
- `GET /api/types`
- `GET /api/resource-type-profiles`
- `GET /api/resource-types/{type}/profile`
- `GET /api/settings`
- `GET /api/tag-keys`
- `GET /api/resources/{id}/dependency-slots`
- `GET /api/type-packs`
- `GET /api/type-packs/installed`
- `GET /api/type-packs/available`

Write workflow:

- `POST /api/changes`
- `POST /api/changes/{changeId}/edits`
- `POST /api/changes/{changeId}/validate`
- `GET /api/changes/{changeId}/diff`
- `POST /api/changes/{changeId}/save`
- `POST /api/changes/{changeId}/discard`
- `POST /api/resources`
- `PATCH /api/resources/{id}`
- `PUT /api/resources/{id}/dependencies/{slot}`
- `PATCH /api/settings/tag-keys/{key}`

Type pack operations:

- `POST /api/type-packs/install`
- `POST /api/type-packs/{name}/enable`
- `POST /api/type-packs/{name}/disable`
- `POST /api/type-packs/{name}/uninstall`

## Documentation

- `docs/product.md`
- `docs/architecture.md`
- `docs/ui-ux.md`
- `docs/type-packs.md`
- `docs/api.md`
- `docs/change-workflow.md`
- `docs/future-ai-integration.md`
- `docs/future-enterprise.md`
