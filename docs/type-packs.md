# Type Packs

Type packs are installable extensions that add:

- Resource types
- Dependency types
- Field metadata for forms
- Validation metadata

## Schema metadata extensions

Entity schema metadata can define:

- `required_tags`: tags that must exist and be non-empty (unless `allow_empty` is explicitly enabled in settings)
- `recommended_tags`: tags that should exist (warning if missing)
- `dependency_slots`: slot-based dependency UX metadata

`dependency_slots` supports:

- `key`
- `relation_type`
- `label`
- `description`
- `direction` (`outgoing` / `incoming`)
- `target_types` / `source_types`
- `multiple`
- `required`

Relation schemas may also define `source_types` and `target_types` to support advanced dependency form filtering and compatibility checks.

## Layout

```text
domain-packs/
  <pack-name>/
    pack.yaml
    schemas/
```

## Installed/enabled state

Cataloga tracks installation state in:

- `registry/type-packs.lock.yaml`

Lock entries store:

- `installed`
- `enabled`

This lock file makes interpretation reproducible across environments.

## UI behavior

- Installed and available packs are shown separately.
- Disabled packs are visible but inactive.
- Disabling/uninstalling shows impact counts for affected resources/dependencies.

## API operations

- `GET /api/type-packs`
- `GET /api/type-packs/installed`
- `GET /api/type-packs/available`
- `POST /api/type-packs/install`
- `POST /api/type-packs/{name}/enable`
- `POST /api/type-packs/{name}/disable`
- `POST /api/type-packs/{name}/uninstall`
