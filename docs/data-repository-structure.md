# Data repository structure

Cataloga reads a canonical `registry/` tree from a Git-managed data repository.

## Canonical layout

```text
registry/
  resources/
    host/
      app-01.yaml
    service/
      web.yaml
    database/
      postgres-main.yaml
  settings.yaml
  type-packs.lock.yaml
  relations/        # advanced, legacy, or imported records only
  views/
    site-overview.yaml
  policies/
    core.yaml
```

## Loader behavior

- `registry/resources` is the canonical resource tree.
- `registry/entities` is read as a legacy import location and is migrated on save.
- `registry/relations` is for advanced, legacy, or imported dependency records.
- Each record must live in exactly one file.
- Cataloga reads `.json`, `.yaml`, and `.yml` files recursively.
- `sourceFilePath` is preserved for diagnostics and viewer/API output.

## Record guidance

- Keep `id` stable across refactors.
- Keep `type` explicit in every resource file.
- Normal dependency slots live inline in resource files under `dependencies:`.
- Use `relations/` only for advanced graph data that cannot be represented as a resource dependency slot.
- Use `views/` for read-only navigation presets.
- Use `policies/` for validation metadata, not imperative workflows.
