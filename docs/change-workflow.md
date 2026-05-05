# Change Workflow

All writes are staged as draft changes.

## Lifecycle

1. `POST /api/changes`
2. Add edits (`/edits`)
3. Validate (`/validate`)
4. Review (`/diff` or UI)
5. Save (`/save`) or discard (`/discard`)

## Guarantees

- Web UI and HTTP API use the same workflow service
- Validation runs before save
- File changes are previewable before save
- Save writes local `registry/` files
- Save is idempotent for already-saved draft changes
- Git commit is not part of the normal save behavior

## Statuses

- `draft`
- `validated`
- `saved`
- `failed`
- `discarded`

Compatibility mapping:

- `applied` -> `saved`
- `committed` -> `saved`
- `aborted` -> `discarded`

## UI labels

User-facing screens use:

- Draft
- Review changes
- Save changes
- Discard

Internal implementation may still use `ChangeService` and operation names.
