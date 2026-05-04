> [!WARNING]
> Legacy v1 artifact: this Node-based read-only container path is retained temporarily for migration reference.
> Cataloga v2 primary runtime is `apps/php` with `docker-compose.yml` at repository root.

# Legacy Docker deployment example (v1)

This runs the old read-only `/api/v1` service backed by a mounted `registry/` data repo.

For v2 use:

```bash
docker compose up --build
```

and open `http://localhost:8080`.
