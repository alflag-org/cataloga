> [!WARNING]
> Deprecated for Cataloga v2: managed hosting is out of scope for the current product direction.
> This document is retained temporarily for historical reference during migration.

# Managed hosting architecture

This document defines the target architecture for Cataloga managed hosting.

The goal is to keep customer data separated from Cataloga runtime code while making onboarding and routine
deployment as automated as possible.

## Goals

- customers manage only their data repository
- private and public repositories are both supported
- Cloudflare credentials remain operator-side only
- deploy, rollback, and drift handling are automated
- operator can see the full control plane state and apply explicit overrides when needed
- the system remains suitable for a future subscription product

## Non-goals

- editing customer repositories from the Cataloga platform
- runtime reads from GitHub during request handling
- customer-owned Cloudflare credentials in the primary product path
- preview environments in the initial production milestone

## Core principles

- `Git for desired state and audit`: customer repositories remain the source of truth for data, and an
  operator-owned `hosting-control` repository remains the source of truth for approved tenant config,
  overrides, and deployment ledgers.
- `DB for operational state`: jobs, locks, webhook deliveries, observed deploy health, and subscription
  status live in the control-plane database.
- `Commit-pinned deploys`: every deployment is tied to an exact customer repo commit, control repo commit,
  and Cataloga platform version.
- `Explicit overrides`: any manual operator action must be recorded in Git, mirrored in operational state,
  and reflected in deployment metadata.
- `Strict uniqueness`: tenant slug, repository binding, hostname, and Cloudflare target conflicts are
  rejected explicitly rather than auto-renamed.

## System boundaries

### Customer repository

Customer repositories are customer-owned and contain Cataloga registry data under `registry/` plus a minimal
tenant manifest.

The platform reads these repositories through a GitHub App installation and never writes back to them.

### GitHub App

The GitHub App is the only supported authentication entry point for customer repositories.

Initial permission scope should remain minimal:

- Contents: read
- Metadata: read

The App is responsible for:

- authorizing read access to private and public repositories
- providing installation metadata and repository identity
- delivering install, uninstall, and repository change webhooks

### Hosting-control repository

`hosting-control` is an operator-owned Git repository that stores desired state and audit-visible changes.

It contains:

- tenant manifests
- environment manifests
- explicit override records
- deployment ledger entries

Normal updates are made automatically by the control plane. Human edits are reserved for exceptional
operator override flows.

### Control plane

The control plane receives GitHub webhooks, validates customer repositories, resolves tenant desired state,
builds deployment jobs, performs reconciles, and records outcomes.

### Operational database

The database stores operational state only. It is not the desired-state source of truth.

It contains:

- tenants and bindings
- GitHub installation state
- deployment jobs and retries
- per-tenant locks
- observed deployment status
- domain claims
- webhook deliveries
- subscription state

### Artifact store

The artifact store keeps immutable deployment artifacts and their hashes so rollback can prefer redeploying
known-good packages instead of rebuilding from scratch.

### Cloudflare

Cloudflare is operator-owned in the managed path. The runtime serves packaged assets only.

## Truth sources

Each concern has exactly one primary source of truth.

- `customer repo`: customer data
- `hosting-control repo`: desired control state and operator-visible audit decisions
- `operational database`: execution state and current observations
- `artifact store`: immutable deployment packages
- `Cloudflare`: currently serving runtime state

No single store is allowed to silently override another store's responsibility.

## Tenant model

The initial product model is `1 tenant = 1 customer repository`.

Each tenant has:

- immutable internal id: `tenantId`
- unique human-readable id: `slug`
- display name: `displayName`

The `slug` is for URLs and operator views. The immutable `tenantId` is for internal references and future
renames.

## Tenant revision

The deploy unit is `tenant revision`.

Each tenant revision is defined by:

- `customerRepoCommitSha`
- `controlRepoCommitSha`
- `platformVersion`

Approvals, deploys, rollbacks, and audits must always refer to this tuple, never only to a branch name.

## Customer repository contract

Customer repositories must provide `registry/` and a minimal manifest.

Recommended manifest:

```yaml
kind: cataloga-tenant
version: 1

tenant:
  slug: acme
  displayName: Acme Corp

registry:
  path: registry

deployment:
  channel: production
```

Customer manifests must not contain:

- Cloudflare account details
- operator credentials
- billing information
- Cataloga runtime version pinning
- override flags

## Hosting-control contract

Recommended repository layout:

```text
hosting-control/
  tenants/
    acme.yaml
  environments/
    production.yaml
  overrides/
    acme/
      2026-03-14T120000Z-deploy-freeze.yaml
  deployments/
    acme/
      dep_2026-03-14T120000Z.json
```

Recommended tenant manifest:

```yaml
tenantId: tnt_01HXYZABCDEFG
slug: acme
displayName: Acme Corp

github:
  installationId: 12345678
  repository: acme/infra-registry
  defaultRef: refs/heads/main
  registryPath: registry
  repositoryNodeId: R_kgDOExample

engine:
  catalogaVersion: v0.2.0

deployment:
  environment: production
  cloudflareTarget: acme-prod
  productionHostname: acme.example.com

policy:
  autoDeploy: true
  suspended: false

statusIntent:
  state: active
```

## Uniqueness rules

The platform must reject onboarding or control-state updates when any of the following conflicts are found:

- duplicate `tenantId`
- duplicate `slug`
- duplicate `github.installationId + github.repository + github.registryPath`
- duplicate `github.repositoryNodeId`
- duplicate `deployment.cloudflareTarget`
- duplicate `deployment.productionHostname`

Conflicts must stop the flow with an explicit `conflict` state. The system must not auto-append suffixes or
mutate customer-provided identifiers silently.

## Override contract

Supported override kinds:

- `deploy_freeze`
- `source_pin`
- `force_rollback`
- `suspend`
- `resume`
- `domain_disable`

Every override record must include:

- tenant identity
- override kind
- reason
- actor
- ticket or change reference
- created timestamp
- optional expiry or explicit manual-clear semantics

Overrides are allowed only through the control plane, even when initiated from an operator UI.

## Onboarding state machine

The onboarding flow is a resumable saga, not a single transaction.

States:

- `detected`
- `access_validating`
- `manifest_validating`
- `uniqueness_checking`
- `provisioning`
- `control_state_committing`
- `initial_reconcile_pending`
- `active`
- `action_required`
- `conflict`
- `failed`

Each step must be idempotent and restartable.

## Approval safety

Any operator approval must bind exactly these values:

- tenant id
- customer repo commit SHA
- control repo commit SHA
- platform version
- target hostname or Cloudflare target

Execution must re-check these values before deploying.

## Deployment metadata direction

Managed hosting needs metadata that separates platform provenance from customer data provenance.

Target shape:

```json
{
  "product": "Cataloga",
  "metadataSchemaVersion": 3,
  "tenant": {
    "id": "tnt_01HXYZABCDEFG",
    "slug": "acme"
  },
  "engine": {
    "version": "v0.2.0",
    "repo": "viasnake/cataloga",
    "commitSha": "..."
  },
  "data": {
    "repo": "acme/infra-registry",
    "ref": "refs/heads/main",
    "commitSha": "...",
    "registryPath": "registry"
  },
  "control": {
    "repo": "operator/hosting-control",
    "commitSha": "...",
    "overrideApplied": false
  }
}
```

## M0-M2 freeze

The following decisions are considered fixed for implementation through M2:

- managed hosting is the primary product path
- private repositories are supported through GitHub App integration
- `hosting-control` is a separate Git repository
- operational state lives in a database
- onboarding and deploy flows are commit-pinned and idempotent
- preview environments are deferred
- uniqueness conflicts fail explicitly
- operator overrides remain visible and Git-backed
