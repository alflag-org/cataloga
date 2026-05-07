# Cloudflare Deployment

Public deployment should use this repository (`viasnake/cataloga`) directly.

## Operation policy (current)

- End users should fork `viasnake/cataloga` and deploy from their own fork.
- Planned login/auth expansion and managed offering are out of current scope.

## Prerequisites

- Cloudflare account
- GitHub fork of `viasnake/cataloga`
- Local tools: `mise`, `wrangler`

## 1. Fork and clone

```bash
git clone https://github.com/<your-account>/cataloga.git
cd cataloga
```

## 2. Login to Cloudflare

```bash
cd crates/cataloga-worker
wrangler login
```

## 3. Create D1

```bash
wrangler d1 create cataloga-<your-suffix>
```

Save these values from output:

- `database_name`
- `database_id`

## 4. Create R2

```bash
wrangler r2 bucket create cataloga-<your-suffix>-snapshots
```

Save this value:

- `bucket_name`

## 5. Update worker config

Edit `crates/cataloga-worker/wrangler.toml`:

- `name` -> unique Worker name in your account
- `[[d1_databases]].database_name` -> your D1 name
- `[[d1_databases]].database_id` -> your D1 ID
- `[[r2_buckets]].bucket_name` -> your R2 bucket name

## 6. Build and deploy (local)

```bash
cd /path/to/cataloga
mise install
mise exec -- pnpm -C apps/web install
mise run build-web
mise run build-worker
mise run worker-deploy
```

## 7. Apply D1 migrations

```bash
cd crates/cataloga-worker
wrangler d1 migrations apply CATALOGA_DB --remote
```

## 8. Verify deploy

- Open Worker URL from deploy output
- Confirm `/api/health` returns success
- Create one Resource Type and one Resource on UI
- Reload page and confirm data persistence

## GitHub Actions deploy (optional)

This repository includes deploy in `.github/workflows/ci.yml` (`deploy` job).

- In `viasnake/cataloga`, push to `master` runs CI and then deploys the demo environment.
- In forks, users can run `workflow_dispatch` on the CI workflow after setting their own secrets.

Required repository secrets:

- `CLOUDFLARE_API_TOKEN`
- `CLOUDFLARE_ACCOUNT_ID`
- `CATALOGA_D1_DATABASE_ID`
- `CATALOGA_R2_BUCKET_NAME`

Fork setup:

1. Create D1 and R2 first (steps 3 and 4 above).
2. Add the four secrets in your fork repository settings.
3. Push your `wrangler.toml` updates.
4. Run `CI` workflow manually from Actions (`workflow_dispatch`).
