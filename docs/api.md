# HTTP API

## Read endpoints

- `GET /api/resources`
- `GET /api/resources/{id}`
- `GET /api/dependencies`
- `GET /api/graph?resource={id}`
- `GET /api/types`
- `GET /api/type-packs`
- `GET /api/type-packs/installed`
- `GET /api/type-packs/available`

Compatibility aliases are kept for existing clients:

- `/api/entities` (alias of resources)
- `/api/relations` (alias of dependencies)
- `/api/domain-packs` (alias of type packs)

## Write workflow endpoints

- `POST /api/changes`
- `POST /api/changes/{changeId}/edits`
- `POST /api/changes/{changeId}/validate`
- `GET /api/changes/{changeId}/diff`
- `POST /api/changes/{changeId}/save`
- `POST /api/changes/{changeId}/discard`

Compatibility aliases are also available:

- `/operations` (alias of `/edits`)
- `/commit` (alias of `/save`)
- `/abort` (alias of `/discard`)

## Type pack write endpoints

- `POST /api/type-packs/install`
- `POST /api/type-packs/{name}/enable`
- `POST /api/type-packs/{name}/disable`
- `POST /api/type-packs/{name}/uninstall`
