# Cataloga

Cataloga is an open source, schema-driven infrastructure catalog for teams that need clear visibility of Resources, Relations, and operational change status.

## Recommended deployment

Cataloga is designed to run well on Cloudflare Workers, D1, and R2.

Use this repository as the single source for Cloudflare deployment.

1. Fork `https://github.com/viasnake/cataloga`
2. Configure Cloudflare credentials for your fork
3. Deploy with Wrangler or GitHub Actions from your fork

## Deployment targets

| Target | Status | Recommended for |
|---|---:|---|
| Cloudflare Workers + D1 + R2 | Recommended | Most users |
| Local standalone + SQLite | Supported | Development and private local use |
| Docker | Optional | Self-hosted environments that do not use Cloudflare |

The project is now operated as a single repository without a separate deploy template repository.

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


## YAML Import/Export format

Cataloga YAML is a catalog dataset format for **Import**, **Export**, and **Snapshot** workflows. It is not a Kubernetes-style API object format: the file carries one top-level `version: 1`, and each Resource is written directly under `resources` without per-Resource `api_version`, `kind`, or nested metadata.

Use the JSON Schema in `schemas/cataloga.v1.schema.json` with YAML Language Server, VS Code, or IntelliJ to get editor completion and validation. For local files, add this comment at the top of a catalog YAML file:

```yaml
# yaml-language-server: $schema=./schemas/cataloga.v1.schema.json
version: 1
```

For files edited outside the repository, point the comment at the published schema URL:

```yaml
# yaml-language-server: $schema=https://raw.githubusercontent.com/viasnake/cataloga/master/schemas/cataloga.v1.schema.json
version: 1
```

A minimal catalog dataset looks like this:

```yaml
version: 1
resource_types: []
resources: []
```

Each Resource uses the flat dataset shape:

```yaml
resources:
  - id: device-edge01
    type: device
    name: edge01
    tags:
      site: kanagawa01
    spec:
      role: router
      mgmt_ip: 10.10.10.1
```

See `examples/catalog.yaml` for a complete example.

## Get started

- Deploy your own instance from this repository (see `docs/deploy/cloudflare.md`).
- For local development and contribution, see [CONTRIBUTING.md](./CONTRIBUTING.md).

## Cloudflare operation policy

- `viasnake/cataloga` is the only deployment source repository.
- Deployment uses fork + Wrangler/GitHub Actions workflow from this repository.

## Future scope

Planned areas such as login/auth expansion or managed offering are intentionally out of current scope.
Current operation only covers self-hosted deployment via user forks of this repository.

## Contributing

Contributions are welcome. For setup, checks, tests, and build commands, start with [CONTRIBUTING.md](./CONTRIBUTING.md).
