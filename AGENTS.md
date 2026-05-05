# Cataloga Agent Instructions

## Product
Cataloga is a Rust-first, schema-driven infrastructure catalog.

- Runtime modes:
  - Local standalone: Rust binary + SQLite
  - Cloudflare managed: Rust Worker + D1 + R2
- Canonical runtime storage is database-backed (SQLite/D1).
- YAML is for import/export and snapshots.

## Terminology
Use only these user-facing terms:

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

Do not use old user-facing `Entity` terminology.

## Architecture rules
- `cataloga-core` contains portable domain logic and validation.
- `cataloga-store` defines store traits.
- `cataloga-store-sqlite` provides local store implementation.
- `cataloga-store-d1` provides Worker/D1 implementation.
- `cataloga-api` is shared application logic.
- `cataloga-server` and `cataloga-worker` are runtime adapters only.

## Runtime/tooling rules
- Local development uses `mise`.
- Keep tasks aligned with `mise.toml` only.
- Do not add or restore PHP runtime code.
- Do not reintroduce Git/file-backed canonical storage assumptions.

## Safety
- Preserve unrelated worktree changes.
- Avoid destructive git commands unless explicitly requested.
