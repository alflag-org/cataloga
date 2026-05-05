#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8787}"
TYPE_ID="site"

echo "[1/6] health"
curl -fsS "$BASE_URL/api/health"

echo "[2/6] create resource type"
curl -fsS -X POST "$BASE_URL/api/resource-types" \
  -H 'content-type: application/json' \
  --data '{"id":"site","title":"Site","group":"Organization","description":"Physical or logical site","fields":[{"name":"code","label":"Code","type":"string","enum_values":[]}],"required_fields":["code"],"list_columns":["metadata.name","spec.code"],"form_layout":[],"detail_sections":[],"references":[],"validation_rules":[]}' >/dev/null

echo "[3/6] list resource types"
curl -fsS "$BASE_URL/api/resource-types"

echo "[4/6] create resource"
curl -fsS -X POST "$BASE_URL/api/resources/$TYPE_ID" \
  -H 'content-type: application/json' \
  --data '{"api_version":"cataloga.io/v1","kind":"Resource","metadata":{"id":"site-1","type":"site","name":"Site 1","tags":{}},"spec":{"code":"S1"},"custom_fields":{},"dependencies":{}}' >/dev/null

echo "[5/6] list resources"
curl -fsS "$BASE_URL/api/resources/$TYPE_ID"

echo "[6/6] export yaml"
curl -fsS "$BASE_URL/api/export" >/dev/null

echo "smoke-worker: ok"
