# Product

## What Cataloga is

Cataloga is a registry application.

It records operational resources (services, hosts, networks, DNS, databases, repositories, cloud accounts) and the dependencies between them.

## Primary objects

- Resource
- Dependency
- Tag metadata
- Type pack
- Draft change

## Tag metadata model

- Resource metadata uses AWS-style key-value tags (`metadata.tags`).
- Common operational metadata (`environment`, `owner`, `site`, `zone`, `lifecycle`, etc.) are tags, not fixed `spec` fields.
- Memo-like content is represented with tags (for example `note`, `todo`, `risk`), not a first-class memo field.
- Reserved prefixes (`cataloga:`, `aws:`) are not user-authored in normal workflows.
- Workspace-level vocabulary and defaults are configured in `registry/settings.yaml`.

## Dependency UX model

- Underlying records remain `Relation`, but normal UI editing is slot-based per resource type.
- Dependency slots are declared by type pack schema metadata and drive guided selection in resource forms.
- Generic dependency create/edit screen remains available as an advanced path.

## Interfaces

- Web UI for daily human operations
- HTTP API for automation/integration

Both interfaces operate the same registry and the same save workflow.

## Write safety model

All writes follow the same path:

1. Create draft change
2. Add edits
3. Validate
4. Review diff
5. Save changes or discard

## Non-goals for core product copy

- Not an AI platform
- Not a generic enterprise governance suite
- Not a cloud management console

Advanced AI or enterprise topics are secondary and documented separately.
