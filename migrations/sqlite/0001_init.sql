CREATE TABLE IF NOT EXISTS catalogs (
  id TEXT PRIMARY KEY,
  title TEXT NOT NULL,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS resource_types (
  catalog_id TEXT NOT NULL,
  type_id TEXT NOT NULL,
  body TEXT NOT NULL,
  PRIMARY KEY (catalog_id, type_id)
);

CREATE TABLE IF NOT EXISTS resources (
  catalog_id TEXT NOT NULL,
  type_id TEXT NOT NULL,
  resource_id TEXT NOT NULL,
  body TEXT NOT NULL,
  PRIMARY KEY (catalog_id, type_id, resource_id)
);

CREATE TABLE IF NOT EXISTS resource_indexes (
  catalog_id TEXT NOT NULL,
  type_id TEXT NOT NULL,
  resource_id TEXT NOT NULL,
  key TEXT NOT NULL,
  value TEXT,
  PRIMARY KEY (catalog_id, type_id, resource_id, key)
);

CREATE TABLE IF NOT EXISTS views (
  catalog_id TEXT NOT NULL,
  view_id TEXT NOT NULL,
  body TEXT NOT NULL,
  PRIMARY KEY (catalog_id, view_id)
);

CREATE TABLE IF NOT EXISTS drafts (
  catalog_id TEXT NOT NULL,
  draft_id TEXT NOT NULL,
  status TEXT NOT NULL,
  body TEXT NOT NULL,
  PRIMARY KEY (catalog_id, draft_id)
);

CREATE TABLE IF NOT EXISTS audit_logs (
  catalog_id TEXT NOT NULL,
  log_id TEXT NOT NULL,
  body TEXT NOT NULL,
  PRIMARY KEY (catalog_id, log_id)
);

CREATE TABLE IF NOT EXISTS snapshots (
  catalog_id TEXT NOT NULL,
  snapshot_id TEXT NOT NULL,
  body TEXT NOT NULL,
  PRIMARY KEY (catalog_id, snapshot_id)
);
