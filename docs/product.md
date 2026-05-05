# Product

## What Cataloga is

Cataloga is a registry application.

It records operational resources (services, hosts, networks, DNS, databases, repositories, cloud accounts) and the dependencies between them.

## Primary objects

- Resource
- Dependency
- Type pack
- Draft change

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
