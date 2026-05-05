# Cataloga Architecture

## Product definition

Cataloga is a Git/file-backed registry for Resources and Dependencies.

- Humans operate it from a simple Web UI.
- External systems operate it from an HTTP API.
- All writes use one shared draft-change workflow.
- Type-specific behavior comes from installable Type Packs.

## Architecture layers

1. Registry data
- Canonical files under `registry/`
- Core persisted objects are resources and dependencies

2. Registry core
- Resource model (`Entity` internally)
- Dependency model (`Relation` internally)
- Registry store and parser/serializer
- Query and validation services
- Diff support

3. Change workflow
- Create draft change
- Apply edit operations
- Validate
- Preview diff
- Save or discard

4. Interfaces
- Web UI
- HTTP API
- Optional CLI helpers
- Optional future MCP integration

5. Type pack system
- Pack manifests under `domain-packs/*/pack.yaml`
- Schema metadata under `domain-packs/*/schemas/`
- Installed/enabled state in `registry/type-packs.lock.yaml`

## Dependency direction

`Web UI / HTTP API -> Application services -> Change workflow -> Registry core -> registry/`

Type packs only extend schemas/metadata. Core does not hard-code domain-specific assumptions.

## Canonical storage

```text
registry/
  entities/
  relations/
  schemas/
  type-packs.lock.yaml
```

## Internal naming note

Current codebase retains some internal names such as `Entity`, `Relation`, and `DomainPackRepository`.
User-facing UI and primary docs use the product terms: Resource, Dependency, Type pack, and Draft change.
