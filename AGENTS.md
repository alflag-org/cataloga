# AGENTS.md

This file defines repository-local instructions for coding agents working in Cataloga.

## Scope and precedence
- This file applies to the repository root and below.
- Nested `AGENTS.md` files may add stricter local rules.
- Follow Codex instruction layering: global instructions + this project file.

## Product definition
Cataloga is a Rust-first, schema-driven infrastructure catalog.

- Runtime modes:
  - Local standalone: Rust binary + SQLite
  - Cloudflare managed: Rust Worker + D1 + R2
- Canonical runtime storage is database-backed (SQLite/D1).
- YAML is for Import/Export and Snapshot workflows, not canonical runtime persistence.

## Required user-facing terminology
Use only these terms in user-facing text:

- Resource
- Resource Type
- Field
- View
- Relation
- Draft
- Validate
- Save
- Discard
- Import
- Export
- Snapshot

Never reintroduce old user-facing `Entity` terminology.

## Architecture boundaries
- `cataloga-core`: portable domain logic and validation.
- `cataloga-store`: store traits.
- `cataloga-store-sqlite`: local store implementation.
- `cataloga-store-d1`: Worker/D1 store implementation.
- `cataloga-api`: shared application logic.
- `cataloga-server` and `cataloga-worker`: runtime adapters only.

## Runtime and tooling constraints
- Local development uses `mise` tasks from `mise.toml`.
- Keep task additions and changes aligned with `mise.toml`.
- Do not add or restore PHP runtime code.
- Do not reintroduce Git/file-backed canonical runtime storage assumptions.

## Standard workflow for agents
1. Inspect current state (`git status`, relevant files, and failing paths).
2. Implement minimal, reviewable changes.
3. Run required checks (see below) with `mise run ...`.
4. Report exactly what passed, what was skipped, and why.

## Required verification commands
After behavior-changing edits, run these in order unless task scope is clearly narrower:

1. `mise run fix`
2. `mise run check`
3. `mise run test`
4. `mise run build`

For full integration validation (before final delivery when feasible):

1. `mise run ci`
2. `mise run db-migrate`
3. `mise run seed`
4. Run targeted runtime smoke checks (for example `/api/health`) against the active environment

### Minimum acceptable narrow checks
- Rust-only edits: `mise run fmt-rust-check` + `mise run lint-rust` + relevant Rust tests
- Web-only edits: `mise run fmt-web-check` + `mise run lint-web` + `mise run test-web`

## Task reference (must stay in sync with `mise.toml`)
- Format:
  - `mise run fmt-rust`
  - `mise run fmt-web`
  - `mise run fix`
- Lint/type checks:
  - `mise run lint-rust`
  - `mise run lint-web`
  - `mise run check`
- Tests:
  - `mise run test-rust`
  - `mise run test-web`
  - `mise run test`
- Build:
  - `mise run build-rust`
  - `mise run build-web`
  - `mise run build-worker`
  - `mise run build`
- Runtime:
  - `mise run serve`
  - `mise run worker-dev`
  - `mise run worker-deploy`
- Data ops:
  - `mise run db-migrate`
  - `mise run seed`
- CI:
  - `mise run ci`

## Safety and git
- Preserve unrelated worktree changes.
- Avoid destructive git commands unless explicitly requested.
- Do not commit secrets, credentials, or machine-local runtime state.
