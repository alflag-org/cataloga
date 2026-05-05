# Cloudflare Deployment

## Prerequisites

```bash
mise install
mise exec -- pnpm -C apps/web install
mise run build
```

## Worker + D1 + R2 setup

```bash
cd crates/cataloga-worker
wrangler d1 create cataloga
wrangler r2 bucket create cataloga-snapshots
# copy database_id into wrangler.toml
```

Update `crates/cataloga-worker/wrangler.toml`:

- `database_id = "replace-me"` is a placeholder.
- Replace it with your real D1 database ID before remote operations.
- Keep placeholder values out of production deploy pipelines.

D1 migrations path is configured as `../../migrations/d1` from `crates/cataloga-worker/wrangler.toml`.

Apply migrations:

```bash
wrangler d1 migrations apply cataloga --local
wrangler d1 migrations apply cataloga --remote
```

## Run and deploy

```bash
cd ../..
mise run worker-dev
mise run worker-deploy
```

## Manual smoke test

```bash
curl http://localhost:8787/api/health

curl -X POST http://localhost:8787/api/resource-types \
  -H 'content-type: application/json' \
  --data '{
    "id":"site",
    "title":"Site",
    "group":"Organization",
    "description":"Physical or logical site",
    "fields":[
      {"name":"code","label":"Code","type":"string","enum_values":[]}
    ],
    "required_fields":["code"],
    "list_columns":["metadata.name","spec.code"],
    "form_layout":[],
    "detail_sections":[],
    "references":[],
    "validation_rules":[]
  }'

curl http://localhost:8787/api/resource-types
```

## SPA routing behavior

`wrangler.toml` assets config uses:

- `not_found_handling = "single-page-application"` for deep links like `/resource-types/vlan`
- `run_worker_first = ["/api/*"]` so all API requests execute Worker logic first
