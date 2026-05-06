# Cataloga

Cataloga is a schema-driven infrastructure catalog built for local-first operations and Cloudflare deployment.

## What Cataloga is
- Rust-first application architecture
- User-defined Resource Types, fields, forms, and views
- Local mode with SQLite runtime storage
- Cloudflare mode with D1 runtime storage and R2 snapshots
- YAML import/export for portability

## What Cataloga is not
- PHP runtime application
- Git/file-backed canonical runtime store
- Fixed network object model
- Type Pack-centric extension model

## Quick start (local)
```bash
mise install
mise exec -- pnpm -C apps/web install
mise run ci
mise run serve
```

## Cloudflare mode
```bash
mise run worker-dev
mise run worker-deploy
```

## CI/CD
- Pull Request: run checks only (`rust`, `web`, `worker`)
- `master` push: run checks, then deploy to Cloudflare (`deploy` job)
- Production deploy uses GitHub Environment `production`
- Production deploy flow:
  1. Build web assets
  2. Build worker
  3. Apply D1 remote migrations
  4. Deploy worker
  5. Run read-only smoke test (`/api/health`) when `CATALOGA_PROD_URL` is set

## Core concepts
- Resource Type: schema for Resources
- Resource: data record validated by Resource Type
- Field: typed property in Resource Type
- View: list/detail presentation definition
- Draft: staged change lifecycle container
- Import/Export: YAML round-trip
- Snapshot: portable state capture

## API surface (initial)
- Resource Type CRUD
- Resource CRUD
- validate
- import
- export

## Development tasks
- `mise run fmt`
- `mise run fmt-check`
- `mise run web-fmt`
- `mise run web-fmt-check`
- `mise run lint`
- `mise run test`
- `mise run build-rust`
- `mise run build-web`
- `mise run build`
- `mise run worker-build`
- `mise run ci`
- `mise run dev`
- `mise run serve`
- `mise run worker-dev`
- `mise run worker-deploy`
- `mise run db-migrate`
- `mise run seed`

## Project structure
- `crates/cataloga-core`
- `crates/cataloga-store`
- `crates/cataloga-store-sqlite`
- `crates/cataloga-store-d1`
- `crates/cataloga-api`
- `crates/cataloga-server`
- `crates/cataloga-worker`
- `crates/cataloga-cli`
- `apps/web`
- `migrations/sqlite`
- `migrations/d1`
- `examples/home-lab`
