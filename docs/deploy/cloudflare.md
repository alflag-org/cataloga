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
wrangler d1 create cataloga-prod
wrangler r2 bucket create cataloga-prod-snapshots
```

`crates/cataloga-worker/wrangler.toml` currently tracks the production D1 `database_id`.

- If you deploy to a different Cloudflare account, update `database_id` in `wrangler.toml`.
- Keep `database_name = "cataloga-prod"` and `bucket_name = "cataloga-prod-snapshots"` aligned with created resources.

D1 migrations path is configured as `../../migrations/d1` from `crates/cataloga-worker/wrangler.toml`.

Apply migrations:

```bash
wrangler d1 migrations apply cataloga-prod --local
wrangler d1 migrations apply cataloga-prod --remote
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
curl http://localhost:8787/api/resource-types
curl http://localhost:8787/api/export
```

Create a Resource Type:

```bash
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
```

Create a Resource:

```bash
curl -X POST http://localhost:8787/api/resources/site \
  -H 'content-type: application/json' \
  --data '{
    "api_version":"cataloga.io/v1",
    "kind":"Resource",
    "metadata":{"id":"site-1","type":"site","name":"Site 1","tags":{}},
    "spec":{"code":"S1"},
    "custom_fields":{},
    "dependencies":{}
  }'
```

List resources:

```bash
curl http://localhost:8787/api/resources/site
```

Optional scripted smoke tests:

```bash
scripts/smoke-worker.sh
BASE_URL=http://127.0.0.1:8080 scripts/smoke-local.sh
```

## SPA routing behavior

`wrangler.toml` assets config uses:

- `not_found_handling = "single-page-application"` for deep links like `/resource-types/vlan`
- `run_worker_first = ["/api/*"]` so all API requests execute Worker logic first
