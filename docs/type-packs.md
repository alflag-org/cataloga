# Type Packs

Type packs are installable extensions that add:

- Resource types
- Dependency types
- Field metadata for forms
- Validation metadata

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
