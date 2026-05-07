# Cloudflare Deployment

Public deployment should use the generated Cloudflare Deploy Button template.

- End-user deployment guide: [cloudflare-button.md](./cloudflare-button.md)
- Template sync workflow: `.github/workflows/sync-cloudflare-template.yml`

## Maintainers: direct monorepo deploy (optional)

Direct deploy from `viasnake/cataloga` is optional and intended for maintainers.
It is not the primary OSS deployment path.

```bash
mise install
mise exec -- pnpm -C apps/web install
mise run build-web
mise run build-worker
mise run worker-deploy
```

If you use D1 migrations manually:

```bash
cd crates/cataloga-worker
wrangler d1 migrations apply CATALOGA_DB --remote
```
