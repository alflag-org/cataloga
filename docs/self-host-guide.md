# Self-host guide (Cataloga v2)

Cataloga v2 primary runtime is the PHP self-hosted app with a file-backed registry and audited change sessions.

## Requirements

- mise
- Docker with Compose
- Git (optional but recommended for diff/commit workflows)

## Quick start

```bash
mise install
mise run verify
```

Check local PHP lint:

```bash
mise run php-lint
```

Open:

```text
http://localhost:8080
```

Stop:

```bash
mise run down
```

## Runtime paths

- Canonical registry: `registry/`
- Runtime state and audits: `.cataloga/`

`docker-compose.yml` mounts both into the container:

- `./registry:/app/registry`
- `./.cataloga:/app/.cataloga`

## Mutation flow

All writes must use change sessions:

1. Create or edit an entity from UI (`/entities/new`, `/entities/{id}/edit`) or API (`POST /api/changes`).
2. Add operations.
3. Validate.
4. Review diff.
5. Commit and optionally create a Git commit.

## API endpoints

- `GET /api/entities`
- `GET /api/entities/{id}`
- `POST /api/changes`
- `GET /api/changes/{id}`
- `POST /api/changes/{id}/operations`
- `POST /api/changes/{id}/validate`
- `GET /api/changes/{id}/diff`
- `POST /api/changes/{id}/commit`
- `POST /api/changes/{id}/abort`

## Notes

- Managed hosting/operator control plane is out of scope for v2.
