# Cataloga

Cataloga is an open source, schema-driven infrastructure catalog for teams that need clear visibility of Resources, Relations, and operational change status.

[![Deploy to Cloudflare](https://deploy.workers.cloudflare.com/button)](https://deploy.workers.cloudflare.com/?url=https://github.com/viasnake/cataloga-cloudflare-template)

## Recommended deployment

Cataloga is designed to run well on Cloudflare Workers, D1, and R2.

Use the Deploy to Cloudflare button above to create your own instance.

## Deployment targets

| Target | Status | Recommended for |
|---|---:|---|
| Cloudflare Workers + D1 + R2 | Recommended | Most users |
| Local standalone + SQLite | Supported | Development and private local use |
| Docker | Optional | Self-hosted environments that do not use Cloudflare |

The Deploy Button uses the generated `cataloga-cloudflare-template` repository because the main Cataloga repository is a Rust workspace monorepo.

## Why Cataloga

Most infra data is scattered across docs, tickets, dashboards, and tribal knowledge. Cataloga gives you one operational catalog where teams can:

- Model infrastructure as **Resources** and **Resource Types**
- Track **Relations** between systems and dependencies
- Manage changes with **Draft**, **Validate**, **Save**, and **Discard** workflows
- Exchange data through **Import**, **Export**, and **Snapshot** flows

## Who it is for

- Platform and SRE teams
- Infrastructure architects
- Internal developer platform owners
- Teams building operational inventories and dependency maps

## Core capabilities

- Schema-driven Resource Type design
- Relationship-aware Resource management
- Validation-first workflow for safer updates
- Local-first runtime and Cloudflare-managed deployment options

## Runtime options

- Local standalone: Rust binary + SQLite
- Cloudflare managed: Rust Worker + D1 + R2

Canonical runtime storage is database-backed (SQLite/D1). YAML is used for Import/Export and Snapshot portability.

## Get started

- Deploy your own instance with the Deploy Button above.
- For local development and contribution, see [CONTRIBUTING.md](./CONTRIBUTING.md).

## Maintainers: template sync

The Deploy Button template repository is updated from this repository.

- Prepare generated template locally: `mise run cloudflare-template-prepare`
- Validate generated template locally: `mise run cloudflare-template-check`
- Sync by release tag (`v*.*.*`) or manual run of `.github/workflows/sync-cloudflare-template.yml`

## Contributing

Contributions are welcome. For setup, checks, tests, and build commands, start with [CONTRIBUTING.md](./CONTRIBUTING.md).
