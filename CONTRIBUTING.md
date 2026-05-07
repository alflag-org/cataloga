# Contributing to Cataloga

Thanks for contributing to Cataloga.

## Development setup

```bash
mise install
mise run install-web
```

## Recommended local workflow

```bash
mise run fix
mise run check
mise run test
mise run build
```

## Local runtime operations

```bash
mise run db-migrate
mise run seed
mise run serve
# in another shell
mise run smoke-local
```

## Cloudflare operations

```bash
mise run worker-dev
mise run build-worker
mise run worker-deploy
```

## Task reference

### Setup

- `mise run install-web`

### Auto-fix / formatting

- `mise run fix`
- `mise run fmt-rust`
- `mise run fmt-web`

### Checks

- `mise run check`
- `mise run lint-rust`
- `mise run lint-web`
- `mise run docs-check`

### Tests

- `mise run test`
- `mise run test-rust`
- `mise run test-web`

### Build

- `mise run build`
- `mise run build-rust`
- `mise run build-web`
- `mise run build-worker`

### Smoke

- `mise run smoke-local`
- `mise run smoke-worker`

### Full CI bundle

- `mise run ci`
