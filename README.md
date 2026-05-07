# Cataloga

Cataloga is an open source, schema-driven infrastructure catalog for teams that need clear visibility of Resources, Relations, and operational change status.

[![Deploy to Cloudflare](https://deploy.workers.cloudflare.com/button)](https://deploy.workers.cloudflare.com/?url=https://github.com/viasnake/cataloga)

Deploy your own instance in minutes, then adapt it to your team.

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

- One-click deploy: use the **Deploy to Cloudflare** button above
- Local development and contribution guide: see [CONTRIBUTING.md](./CONTRIBUTING.md)

## Contributing

Contributions are welcome. For setup, checks, tests, and build commands, start with [CONTRIBUTING.md](./CONTRIBUTING.md).
