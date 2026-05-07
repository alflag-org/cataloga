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
wrangler d1 create cataloga-demo
wrangler r2 bucket create cataloga-demo-snapshots
```

`crates/cataloga-worker/wrangler.toml` uses demo placeholders by default.

- Set `database_id` in `wrangler.toml` to your created D1 database ID.
- Keep `database_name = "cataloga-demo"` and `bucket_name = "cataloga-demo-snapshots"` aligned with created resources, or rename all of them consistently.

D1 migrations path is configured as `../../migrations/d1` from `crates/cataloga-worker/wrangler.toml`.

Apply migrations:

```bash
wrangler d1 migrations apply cataloga-demo --local
wrangler d1 migrations apply cataloga-demo --remote
```

## Run and deploy

```bash
cd ../..
mise run worker-dev
mise run worker-deploy
```

CI deploy uses the same ordering with explicit checks:

1. `mise run build-web`
2. `mise run worker-build`
3. `wrangler d1 migrations apply cataloga-demo --remote`
4. `wrangler deploy`
5. `curl -fsS "${CATALOGA_PROD_URL}/api/health"` (read-only smoke, when configured)

Cloudflare authentication in CI must use:

- `CLOUDFLARE_API_TOKEN`
- `CLOUDFLARE_ACCOUNT_ID`

## Observability

Workers Logs, traces, and source maps are enabled in `wrangler.toml`.

```bash
cd crates/cataloga-worker
wrangler tail
```

Use this to watch real-time logs after deployment.

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

Production smoke in GitHub Actions should call only read-only endpoints (currently `/api/health`).

## SPA routing behavior

`wrangler.toml` assets config uses:

- `not_found_handling = "single-page-application"` for deep links like `/resource-types/vlan`
- `run_worker_first = ["/api/*"]` so all API requests execute Worker logic first
