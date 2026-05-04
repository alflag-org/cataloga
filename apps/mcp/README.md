# Cataloga MCP (Planned)

This directory is reserved for a future Cataloga MCP server implementation.

Planned direction:

- Resources
  - Registry snapshot views (`entities`, `relations`, `schemas`, `policies`, `views`, `evidence`)
  - Change session state (`open`, `validated`, `committed`, `aborted`)
- Tools
  - `start_change`
  - `apply_mutation`
  - `validate_change`
  - `show_diff`
  - `commit_change`
  - `abort_change`

All mutation-capable MCP tools must use the same PHP mutation/change-session engine used by UI/API.
