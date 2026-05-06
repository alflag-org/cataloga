# Observability

Cataloga Worker observability is configured in `crates/cataloga-worker/wrangler.toml`.

- Workers Logs: enabled
- Workers Traces: enabled
- Source maps upload: enabled

## Real-time log tail

```bash
cd crates/cataloga-worker
wrangler tail
```

Pretty output:

```bash
wrangler tail --format pretty
```

## Cloudflare dashboard

Use Cloudflare dashboard Workers Logs and Traces views for production troubleshooting.

## Custom log fields

Custom logs are one-line JSON.

Required fields:

- `event`
- `method`
- `path`
- `route`
- `status`
- `duration_ms`
- `catalog_id`

Optional fields:

- `cf_ray`
- `target_type`
- `target_id`
- `error_kind`
- `error_message`

## Event names

- `api_request_completed`
- `api_request_failed`
- `api_request_not_found`
- `api_request_bad_input`
- `api_health_check`
- `resource_type_upserted`
- `resource_type_deleted`
- `resource_upserted`
- `resource_deleted`
- `import_preview_completed`
- `import_apply_completed`
- `export_completed`
- `validation_completed`

## Redaction policy

Never log:

- full Resource JSON body
- YAML import body
- YAML export body
- request headers
- authorization tokens
- cookies
- Cloudflare Access JWT
- large response bodies

`target_type` and `target_id` are allowed.

## Sampling policy

Current policy is full sampling for stabilization:

- logs `head_sampling_rate = 1.0`
- traces `head_sampling_rate = 1.0`

Later, traffic-based reduction can be applied.
