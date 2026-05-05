# Cataloga Development Instructions

Cataloga is an AI-native, Git/file-backed, domain-agnostic registry platform.

## Product Direction

- Cataloga is a registry platform, not a network-specific product.
- Network inventory is a domain pack/example, not the core identity.
- Managed hosting is out of scope unless explicitly requested.
- Backward compatibility with older abstractions is not required.
- Prefer simple, local-first, self-hostable architecture.

## Runtime and Tooling Rules

- PHP is the only implementation runtime.
- Do not create or restore Node.js / TypeScript app code unless explicitly requested.
- Do not add npm workspace tooling.
- Docker Compose and Composer are the expected development/runtime tools.

## Architecture Rules

- Git/file-backed registry data is the source of truth.
- Runtime databases may only be used for cache, index, lock, audit, or derived state.
- Core must define generic registry mechanics:
  - Entity
  - Relation
  - Schema
  - View
  - Policy
  - Evidence
  - ChangeSession
  - ValidationRule
- Domain semantics belong in packs.
- Human UI, CLI, API, and MCP tools must share the same mutation engine.
- Do not create direct write paths that bypass change sessions.
- All writes must continue to go through PHP change sessions.
- Future MCP must call the same PHP mutation/change-session path.

## AI-Native Rules

- MCP is a first-class interface.
- AI agents must be able to explore, validate, edit, diff, and commit registry data.
- AI mutations must be auditable and reviewable.
- Prefer semantic mutation tools over raw file writes.
- All write operations must support validation and diff preview.

## Coding Rules

- Avoid network-specific assumptions in core packages.
- Avoid managed-hosting/operator/tenant abstractions unless explicitly requested.
- Preserve unrelated worktree changes.
- Do not use destructive git commands without explicit approval.
