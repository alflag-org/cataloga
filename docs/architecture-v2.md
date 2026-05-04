# Cataloga v2 Architecture

## Strategic definition

Cataloga v2 is an AI-native, Git/file-backed, domain-agnostic registry platform.

Core behavior is domain-neutral. Domain semantics belong in packs.

## Goals

- Keep Git/file data as the source of truth.
- Support safe writes with auditable change sessions.
- Expose the same write path to humans and AI agents.
- Separate core mechanics from domain packs.
- Support enterprise self-hosting controls (RBAC, policy, audit, governance).

## Non-goals (v2)

- Managed hosting control plane.
- Multi-tenant SaaS operator abstractions.
- Network-specific assumptions in core models.

## Core primitives

All core records are file-backed and typed.

- `Entity`: typed record with identity and attributes.
- `Relation`: directed edge between entities.
- `Schema`: attribute constraints, identity rules, and relation constraints.
- `View`: reusable query/projection definition.
- `Policy`: governance constraints and enforcement mode.
- `Evidence`: provenance/observation/attachment metadata.
- `ValidationRule`: executable validation definition with severity and ownership.
- `ChangeSession`: staged mutable workspace with audit metadata and lifecycle state.

## Architecture layers

1. Registry Core
- Type definitions and canonical serialization.
- File layout reader/writer.
- Semantic diff between registry revisions.

2. Mutation Engine
- Change session lifecycle.
- Mutation operation application.
- Validation pipeline.
- Commit/abort orchestration.

3. Derived State
- Optional SQLite/local DB caches for search index, lock state, audit materialization, and runtime session artifacts (for example `.cataloga/`).
- Never the source of truth for canonical registry content.
- Runtime/derived paths (including `.cataloga/`) are non-canonical and should be excluded from default Git staging/commit flows.

4. Interfaces
- CLI, API, UI, MCP tools/resources/prompts.
- All writes route through mutation engine; no direct ad hoc file writes.

5. Domain Packs
- Domain schemas, policies, default views, and validation rules.
- Core never hardcodes network/cloud/dns/service semantics.

## Git/file model

Canonical storage (example):

```text
registry/
  schemas/
  entities/
  relations/
  views/
  policies/
  evidence/
```

Rules:

- Canonical records live as `yaml` or `json` files.
- Every record includes stable IDs and traceable source paths.
- Runtime DB/cache is rebuildable from files.

## Mutation lifecycle

Mutation API contract:

1. `start_change` creates a `ChangeSession`.
2. `apply_mutation` applies typed operations.
3. `validate_change` runs schema/policy/rule checks.
4. `show_diff` returns semantic and file-level diffs.
5. `commit_change` writes canonical files and creates a Git commit (or staged commit input).
6. `abort_change` discards staged changes.


Audit retention note:

- Default runtime audit logs under `.cataloga/` are derived operational artifacts and non-Git-managed.
- If `.cataloga/` was tracked in earlier revisions, remove from index with `git rm -r --cached .cataloga` and commit the change to enforce runtime-only handling.
- For persistent, reviewable audit history in canonical data, explicitly model and store it under `registry/` (for example `registry/audit/`) with schema/policy coverage.

## MCP-first interface

MCP surfaces:

- Resources: registry snapshots, schemas, policy sets, views, change session state.
- Tools: query + mutation lifecycle tools using change sessions.
- Prompts: guided workflows (safe edit, review failing validation, generate pack skeleton).

Permissioning:

- Tool-level ACL/RBAC boundaries.
- Session ownership and lock scopes.
- Full audit trail from prompt/tool invocation to committed diff.

## Enterprise-capable self-hosting

v2 self-hosting scope includes:

- RBAC and policy ownership boundaries.
- Auditable change history.
- Git workflow integration (branch/PR/approval policy hooks).
- MCP tool permissioning and governance controls.

Managed hosting and operator automation are explicitly out of scope for v2.

## Initial package boundaries

- `packages/registry`: v2 primitives and mutation models.
- `packages/mcp`: MCP contract placeholders for registry exploration/mutation.
- `domain-packs/*`: pack manifests/schemas/policies/views.
- Existing v1 packages remain temporarily and are progressively migrated or retired.
