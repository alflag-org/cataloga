# Cloudflare Deployment

Cataloga Cloudflare mode uses:

- Rust Worker (`workers-rs`)
- D1 for Resource Type and Resource runtime CRUD
- R2 for snapshot storage (next step)
- Static Assets for the web UI

## Setup
1. Build web assets:
   ```bash
   mise exec -- pnpm -C apps/web install
   mise exec -- pnpm -C apps/web build
   ```
2. Configure `crates/cataloga-worker/wrangler.toml` bindings:
   - `CATALOGA_DB` (D1)
   - `CATALOGA_SNAPSHOTS` (R2)
3. Apply migrations in `migrations/d1`.

## Development
```bash
mise run worker-dev
```

## Deploy
```bash
mise run worker-deploy
```
