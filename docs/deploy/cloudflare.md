# Cloudflare Deployment

Public deployment should use this repository (`viasnake/cataloga`) directly.

## Operation policy (current)

- End users should fork `viasnake/cataloga` and deploy from their own fork.
- Planned login/auth expansion and managed offering are out of current scope.

## End-user deploy (fork + Wrangler)

```bash
mise install
mise exec -- pnpm -C apps/web install
mise run build-web
mise run build-worker
mise run worker-deploy
```

If you apply D1 migrations manually:

```bash
cd crates/cataloga-worker
wrangler d1 migrations apply CATALOGA_DB --remote
```

## Deploy from GitHub Actions

This repository includes deploy in `.github/workflows/ci.yml` (`deploy` job).

- In `viasnake/cataloga`, push to `master` runs CI and then deploys the demo environment.
- In forks, users can run `workflow_dispatch` on the CI workflow after setting their own secrets.

Required repository secrets:

- `CLOUDFLARE_API_TOKEN`
- `CLOUDFLARE_ACCOUNT_ID`
- `CATALOGA_D1_DATABASE_ID`
- `CATALOGA_R2_BUCKET_NAME`

Fork setup notes:

1. Create D1 and R2 resources in your Cloudflare account.
2. Set the four secrets in your fork repository settings.
3. Update `crates/cataloga-worker/wrangler.toml` worker name and non-secret settings as needed.
4. Run `CI` workflow manually from Actions (`workflow_dispatch`).
