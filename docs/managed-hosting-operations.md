> [!WARNING]
> Deprecated for Cataloga v2: managed hosting is out of scope for the current product direction.
> This document is retained temporarily for historical reference during migration.

# Managed hosting operations

This document captures operator-facing runbooks and the production rollout path for Cataloga managed hosting.

It covers the M5 and M6 phases: visibility, drift handling, overrides, rollout, and subscription-oriented
operations.

## Operator visibility requirements

Each tenant view must show at least:

- tenant id and slug
- current desired tenant revision
- last successful deployed tenant revision
- active overrides
- GitHub App installation health
- customer repository binding
- production hostname and Cloudflare target
- most recent failures
- drift status
- subscription state

## Required audit surfaces

The same operator action must be visible in all of these places:

- control plane database event log
- `hosting-control` Git history
- deployment ledger
- deployment metadata for the currently serving package

## Drift model

Drift exists when any of the following differ:

- `hosting-control` desired state and database effective state
- database effective state and actual Cloudflare state
- currently served artifact and last successful deployment record
- expected GitHub App access and real repository access

Drift categories:

- `control_vs_db`
- `db_vs_runtime`
- `runtime_vs_ledger`
- `access_vs_binding`

## Runbook: onboarding failure

Use when a tenant does not reach `active`.

Checklist:

1. inspect onboarding state and failure category
2. verify GitHub App installation is still valid
3. verify manifest and `registry/` path in customer repo
4. verify uniqueness claims for slug, hostname, and repository binding
5. retry the failed saga step
6. if still blocked, move tenant to `action_required` with a clear reason code

## Runbook: failed deployment

Use when build, package, deploy, or verification fails.

Checklist:

1. inspect deployment failure category and code
2. confirm tenant lock ownership and stale-lock status
3. verify the target tenant revision is still current
4. rerun the deployment if the failure is retriable
5. if the failure is non-retriable, freeze auto deploy and open an operator ticket
6. if production is unhealthy, roll back to the previous successful deployment

## Runbook: rollback

Rollback priority:

1. redeploy the previous successful artifact
2. rebuild and redeploy the previous successful tenant revision
3. use Cloudflare native rollback only for emergency containment

Checklist:

1. identify the last known-good deployment
2. record a `force_rollback` override with reason and ticket
3. execute rollback against the exact target deployment or tenant revision
4. verify `/health`, `/bundle.json`, `/api/views`, and `/api/metadata`
5. clear or update overrides after the incident is understood

## Runbook: GitHub App access revoked

Use when a private repository becomes unreadable.

Checklist:

1. detect uninstall or access revoke from webhook or scheduled health check
2. mark tenant `degraded`
3. stop auto deploy for the tenant
4. keep serving the last known-good deployment unless suspension policy requires otherwise
5. notify operator and customer with the reason code
6. resume normal reconcile only after access is restored

## Runbook: deploy freeze

Use when customer changes must not reach production.

Checklist:

1. create a `deploy_freeze` override
2. confirm override is visible in Git, DB, and operator view
3. ensure reconcile results become `blocked` rather than silent no-op
4. clear the freeze through an explicit override removal or expiry

## Runbook: suspension

Use for billing, abuse, or manual service stop.

Checklist:

1. record a `suspend` override or policy change
2. disable auto deploy
3. decide whether the current deployment continues serving or is disabled according to subscription policy
4. log the reason code and timeline
5. require an explicit `resume` action to restore service

## Production rollout milestones

### M1: Private alpha

Scope:

- 1-3 tenants
- manual onboarding trigger
- production only
- operator-supervised deploys

Exit criteria:

- successful onboarding for at least one private repo tenant
- successful rollback drill
- uniqueness conflict drill completed

### M2: Managed beta

Scope:

- self-serve GitHub App install flow
- auto deploy from default branch
- operator dashboard and drift detection

Exit criteria:

- repeated successful onboarding without direct operator config editing
- access revoke detection works
- rollback and suspend flows exercised

### M3: Paid GA

Scope:

- subscription and entitlement hooks enabled
- runbooks finalized
- production readiness checks automated

Exit criteria:

- incident drills completed
- support ownership and escalation path documented
- rollout checklist signed off

## Production readiness checklist

- architecture ADR approved
- schemas frozen for current release line
- uniqueness constraints enforced in DB and reconcile logic
- onboarding saga is resumable and idempotent
- deployment metadata includes tenant, engine, data, and control provenance
- rollback to previous successful artifact is verified
- GitHub App revoke handling is verified
- drift detection is enabled
- operator overrides are visible in all required surfaces
- subscription suspend policy is documented
