# Migration to Cataloga v2

## Purpose

This plan migrates Cataloga from v1 read-only/network-leaning architecture to a domain-agnostic registry platform with a shared mutation engine and MCP-first AI workflow.

Backward compatibility is not required.

## Current-state summary

The repository currently contains:

- v1 read-only graph loader/API/viewer contracts.
- network-oriented built-in entity and validation assumptions.
- ingest/snapshot/topology/drift runtime packages.
- managed-hosting docs, tests, and package contracts.

## Keep / Replace / Deprecate matrix

### Keep and reuse

- `packages/core` file loading patterns (recursive file scan, source path tracking).
- `packages/validator` structural validation shape (diagnostic model), but domain rules must move to packs.
- CLI/API/web scaffolding as interface shells.
- example registries as migration fixtures.

### Replace (v2 target)

- `packages/types` network-specific primitives -> replace with domain-neutral primitives in `packages/registry`.
- `packages/schemas` built-in network entity schemas -> replace by pack-owned schemas.
- write-less runtime pattern -> replace with mutation engine and change sessions.
- direct read-only API identity -> evolve into query + mutation-session API.

### Deprecate (v1 legacy)

- `packages/managed-hosting` and `docs/managed-hosting-*.md`.
- `docs/read-only-and-delivery-model.md` as strategic product definition.
- network-specific phrasing in root README.

## Target package direction

- `packages/registry`
  - canonical v2 primitive types
  - change-session model
  - mutation operation model

- `packages/mcp`
  - MCP resources/tools/prompts contract placeholders
  - no direct file write path; mutation tools route to change sessions

- `domain-packs/*`
  - per-domain schema/policy/view/rule bundles

## Phase plan

### Phase 0 (this change)

- publish v2 architecture and migration docs.
- pivot README product definition.
- add v2 package skeletons and domain-pack scaffolding.
- mark managed-hosting artifacts deprecated.

### Phase 1

- implement registry layout loader for v2 primitives.
- implement mutation engine state machine + lock model.
- add semantic diff model for change sessions.
- wire CLI commands to mutation lifecycle.

### Phase 2

- add MCP resources/tools/prompts for exploration + mutations.
- add authorization and audit envelope around mutation tools.
- add pack loader/activation model and compose validation rules.

### Phase 3

- migrate API/web flows from v1 read-only graph endpoints to v2 query + change-session UX.
- retire or archive v1-only packages after replacement coverage.

## Immediate deletion/deprecation candidates

Safe now:

- Mark managed-hosting docs as deprecated and out of scope.
- Mark `@cataloga/managed-hosting` as deprecated legacy package.

Later after replacement:

- Remove managed-hosting tests from default test matrix.
- Remove managed-hosting package exports and docs.

## Risks and controls

- Risk: v1 and v2 codepaths diverge and confuse contributors.
  - Control: central v2 docs and explicit deprecation banners.
- Risk: mutation engine introduced without uniform adoption.
  - Control: prohibit new write paths outside change sessions.
- Risk: domain logic leaks into core during migration.
  - Control: require all domain semantics to be pack-owned.

## Definition of done for v2 pivot bootstrap

- Product docs clearly identify Cataloga as domain-agnostic registry platform.
- Core v2 primitives exist in code with explicit types.
- Change session and mutation operation models exist in code.
- Domain pack and MCP scaffolds are in repository.
- Managed hosting is explicitly deprecated for current direction.
