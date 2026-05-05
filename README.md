# Cataloga

Cataloga is a simple registry for resources and dependencies.

It helps teams record infrastructure and service resources, connect them with dependencies, review draft changes, and save updates safely.

- Humans use the Web UI.
- Automation uses the HTTP API.
- Type packs add resource types, dependency types, form fields, and validation rules.

## Product model

- `Resource`: a registered catalog item (service, host, VM, VLAN, DNS record, database, repository, cloud account, etc.)
- `Dependency`: a directed relationship between resources (`runs_on`, `uses`, `belongs_to`, `resolves_to`, etc.)
- `Type pack`: installable extension that provides types, schema metadata, and validation rules
- `Draft change`: staged edits reviewed and validated before save

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
domain-packs/
docs/
```

## Core behavior

- Registry files under `registry/` are canonical source of truth.
- Web UI and HTTP API use the same draft-change workflow for writes.
- Save is blocked when validation errors exist.
- Technical Git/file details are available as advanced/diagnostic views.

## API summary

Read:

- `GET /api/resources`
- `GET /api/resources/{id}`
- `GET /api/dependencies`
- `GET /api/graph?resource={id}`
- `GET /api/types`
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
