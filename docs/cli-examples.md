# CLI/API examples (v2)

Start local app first:

```bash
mise install
mise run verify
```

## List entities

```bash
curl -sS http://127.0.0.1:8080/api/entities
```

## Get one entity

```bash
curl -sS http://127.0.0.1:8080/api/entities/entity-example-entity
```

## Create change session

```bash
curl -sS -X POST http://127.0.0.1:8080/api/changes
```

## Validate change session

```bash
CHANGE_ID="<change-id>"
curl -sS -X POST "http://127.0.0.1:8080/api/changes/${CHANGE_ID}/validate"
```

## Preview diff

```bash
curl -sS "http://127.0.0.1:8080/api/changes/${CHANGE_ID}/diff"
```

## Stop local app

```bash
mise run down
```
