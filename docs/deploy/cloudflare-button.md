# Cloudflare Deploy Button

## Why this uses a generated template repository

`viasnake/cataloga` is the main development repository and source of truth.
It is a Rust workspace monorepo, which is not ideal as a direct Cloudflare Deploy Button source.

To make one-click deployment reliable, Cataloga publishes a generated repository:

- Source of truth: `https://github.com/viasnake/cataloga`
- Generated deploy template: `https://github.com/viasnake/cataloga-cloudflare-template`

Do not edit the template repository manually.
All changes must be made in the main Cataloga repository.

## Deploy Button URL

[![Deploy to Cloudflare](https://deploy.workers.cloudflare.com/button)](https://deploy.workers.cloudflare.com/?url=https://github.com/viasnake/cataloga-cloudflare-template)

## How it works

1. Build web and Worker artifacts in `viasnake/cataloga`.
2. Assemble a generated template into `dist/cloudflare-template/`.
3. Sync generated files to `viasnake/cataloga-cloudflare-template` (`main` branch).
4. Cloudflare Deploy Button reads that generated repository root and provisions resources from `wrangler.toml`.

Generated template root contains:

- `README.md`
- `package.json`
- `wrangler.toml`
- `migrations/d1/`
- `public/`
- `worker/`
- `VERSION`

## Manual update flow

From the main repository root:

```bash
mise run cloudflare-template-prepare
mise run cloudflare-template-check
```

Then either:

- Push a release tag (`v*.*.*`) to run `.github/workflows/sync-cloudflare-template.yml`, or
- Run the same workflow manually via `workflow_dispatch`.

## Required GitHub configuration

- Secret in `viasnake/cataloga`: `CATALOGA_TEMPLATE_REPO_TOKEN`
  - Must have write access to `viasnake/cataloga-cloudflare-template`.
- Target repository: `viasnake/cataloga-cloudflare-template`
- Target branch: `main`

## Smoke test checklist

1. Open the Deploy Button URL.
2. Confirm D1 and R2 resources are detected in the deployment UI.
3. Confirm deployment completes successfully.
4. Open the deployed Worker URL.
5. Create a simple Resource Type or Resource.
6. Refresh and confirm persisted data remains available.
