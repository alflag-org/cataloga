# Product

## What Cataloga is

Cataloga is a registry application.

It records operational resources (services, hosts, networks, DNS, databases, repositories, cloud accounts) and optional hard dependencies between them.

## Primary objects

- Resource
- Dependency
- Tag metadata
- Type pack
- Draft change

## Tag metadata model

- Resource metadata uses AWS-style key-value tags (`metadata.tags`).
- Common operational metadata (`environment`, `owner`, `site`, `zone`, `lifecycle`, etc.) are tags, not fixed `spec` fields.
- `resource_type_profiles` define which management tags are shown prominently per type.
- Memo-like content is represented with tags (for example `note`, `todo`, `risk`), not a first-class memo field.
- Reserved prefixes (`cataloga:`) are not user-authored in normal workflows.
- Workspace-level vocabulary and defaults are configured in `registry/settings.yaml`.
- Tag-based associations are soft metadata for grouping/filtering/search, not graph edges.

## Dependency UX model

- Normal dependency slots are stored in each resource file under `dependencies:` and are optional for resource validity.
- Runtime relation views are derived from resource dependency slot maps for list, graph, API, and validation.
- Generic dependency create/edit screen remains available as an advanced path.

## Canonical file model

- `registry/resources/{type}/{resource}.yaml` is the normal source of truth.
- `registry/entities` is legacy input only.
- `registry/relations` is reserved for advanced, legacy, or imported graph data.
- `registry/settings.yaml` stores workspace tag vocabulary.

## Interfaces

- Web UI for daily human operations
- HTTP API for automation/integration

Both interfaces operate the same registry and the same save workflow.

## Write safety model

All writes follow the same path:

1. Create draft change
2. Add edits
3. Validate
4. Review diff
5. Save changes to local registry files or discard

Git is not part of the core save flow. Optional integrations (Git sync/history, SQLite, MySQL, remote registry backends) can be added later.

## Non-goals for core product copy

- Not an AI platform
- Not a generic enterprise governance suite
- Not a cloud management console

Advanced AI or enterprise topics are secondary and documented separately.
