# HTTP API

## Read endpoints

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

Compatibility aliases are kept for existing clients:

- `/api/entities` (alias of resources)
- `/api/relations` (alias of dependencies)
- `/api/domain-packs` (alias of type packs)
- `/api/entities/{id}/dependency-slots` (alias of `/api/resources/{id}/dependency-slots`)

## Metadata notes

- Resource tags are key-value under `metadata.tags`.
- New writes should not use list-style tags.
- Reserved tag prefixes (`cataloga:`) are treated as system-reserved.
- `resource_type_profiles` and `default_management_tags` are read from `registry/settings.yaml`.
- `dependencies` are optional; missing targets or slot-recommendation gaps are warnings unless data shape is invalid.

## Write workflow endpoints

- `POST /api/changes`
- `POST /api/changes/{changeId}/edits`
- `POST /api/changes/{changeId}/validate`
- `GET /api/changes/{changeId}/diff`
- `POST /api/changes/{changeId}/save`
- `POST /api/changes/{changeId}/discard`

`save` applies validated changes to local registry files (`registry/`). Git is not part of the core save flow.

Compatibility aliases are also available:

- `/operations` (alias of `/edits`)
- `/commit` (alias of `/save`)
- `/abort` (alias of `/discard`)

## Type pack write endpoints

- `POST /api/type-packs/install`
- `POST /api/type-packs/{name}/enable`
- `POST /api/type-packs/{name}/disable`
- `POST /api/type-packs/{name}/uninstall`
