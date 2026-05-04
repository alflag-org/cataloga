> [!WARNING]
> Legacy v1 delivery model: this read-only/static-first model is not the Cataloga v2 write architecture.
> Kept temporarily while v2 mutation/session interfaces are implemented.

# Read-only and static-first delivery model

## Read-only contract

Cataloga CLI/API/viewer are read-only views of registry data.

- No write endpoint is exposed.
- Data changes happen through commits in the registry data Git repository.
- Diagnostics include `sourceFilePath` so reviewers can trace entities, relations, views, and policies to versioned files.

## Static-first workflow

1. Validate data:

   ```bash
   npm exec --workspace @cataloga/cli cataloga -- validate --registry <registry_repo_path>
   ```

2. Build/export static bundle:

   ```bash
   npm exec --workspace @cataloga/cli cataloga -- export --registry <registry_repo_path> --out dist/bundle.json
   ```

3. Serve `bundle.json` directly from static hosting or wrap the same registry in read-only API endpoints.

## Why this model

- Operationally simple and cache-friendly.
- Strong audit trail through Git history.
- Clear trust boundary: the registry Git repo is the single source of truth.
