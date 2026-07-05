use async_trait::async_trait;
use cataloga_core::{Resource, ResourceType};
use cataloga_store::CatalogStore;
use sqlx::{
    Row, SqlitePool,
    sqlite::{SqliteConnectOptions, SqlitePoolOptions},
};
use std::str::FromStr;

pub struct SqliteStore {
    pool: SqlitePool,
}

impl SqliteStore {
    pub async fn connect(db_url: &str) -> anyhow::Result<Self> {
        let options = SqliteConnectOptions::from_str(db_url)?.create_if_missing(true);
        let pool = SqlitePoolOptions::new()
            .max_connections(1)
            .connect_with(options)
            .await?;

        sqlx::query(
            "CREATE TABLE IF NOT EXISTS catalogs (
                id TEXT PRIMARY KEY,
                title TEXT NOT NULL,
                created_at TEXT NOT NULL
            )",
        )
        .execute(&pool)
        .await?;

        sqlx::query(
            "CREATE TABLE IF NOT EXISTS resource_types (
                catalog_id TEXT NOT NULL,
                type_id TEXT NOT NULL,
                body TEXT NOT NULL,
                PRIMARY KEY (catalog_id, type_id)
            )",
        )
        .execute(&pool)
        .await?;

        sqlx::query(
            "CREATE TABLE IF NOT EXISTS resources (
                catalog_id TEXT NOT NULL,
                type_id TEXT NOT NULL,
                resource_id TEXT NOT NULL,
                body TEXT NOT NULL,
                PRIMARY KEY (catalog_id, type_id, resource_id)
            )",
        )
        .execute(&pool)
        .await?;

        sqlx::query(
            "CREATE TABLE IF NOT EXISTS resource_indexes (
                catalog_id TEXT NOT NULL,
                type_id TEXT NOT NULL,
                resource_id TEXT NOT NULL,
                key TEXT NOT NULL,
                value TEXT,
                PRIMARY KEY (catalog_id, type_id, resource_id, key)
            )",
        )
        .execute(&pool)
        .await?;

        sqlx::query(
            "CREATE TABLE IF NOT EXISTS views (
                catalog_id TEXT NOT NULL,
                view_id TEXT NOT NULL,
                body TEXT NOT NULL,
                PRIMARY KEY (catalog_id, view_id)
            )",
        )
        .execute(&pool)
        .await?;

        sqlx::query(
            "CREATE TABLE IF NOT EXISTS drafts (
                catalog_id TEXT NOT NULL,
                draft_id TEXT NOT NULL,
                status TEXT NOT NULL,
                body TEXT NOT NULL,
                PRIMARY KEY (catalog_id, draft_id)
            )",
        )
        .execute(&pool)
        .await?;

        sqlx::query(
            "CREATE TABLE IF NOT EXISTS audit_logs (
                catalog_id TEXT NOT NULL,
                log_id TEXT NOT NULL,
                body TEXT NOT NULL,
                PRIMARY KEY (catalog_id, log_id)
            )",
        )
        .execute(&pool)
        .await?;

        sqlx::query(
            "CREATE TABLE IF NOT EXISTS snapshots (
                catalog_id TEXT NOT NULL,
                snapshot_id TEXT NOT NULL,
                body TEXT NOT NULL,
                PRIMARY KEY (catalog_id, snapshot_id)
            )",
        )
        .execute(&pool)
        .await?;

        Ok(Self { pool })
    }

    fn decode_resource_row(
        body: &str,
        fallback_type_id: Option<&str>,
        fallback_resource_id: Option<&str>,
    ) -> anyhow::Result<Resource> {
        let mut json: serde_json::Value = serde_json::from_str(body)?;
        let obj = json
            .as_object_mut()
            .ok_or_else(|| anyhow::anyhow!("resource body must be a JSON object"))?;
        let resource_id = fallback_resource_id.unwrap_or_default();
        let type_id = fallback_type_id.unwrap_or_default();
        if !obj.contains_key("id") {
            obj.insert(
                "id".to_string(),
                serde_json::Value::String(resource_id.to_string()),
            );
        }
        if !obj.contains_key("type") && !obj.contains_key("resource_type") {
            obj.insert(
                "type".to_string(),
                serde_json::Value::String(type_id.to_string()),
            );
        }
        if !obj.contains_key("name") {
            obj.insert(
                "name".to_string(),
                serde_json::Value::String(resource_id.to_string()),
            );
        }
        Ok(serde_json::from_value::<Resource>(json)?)
    }
}

#[cfg_attr(target_arch = "wasm32", async_trait(?Send))]
#[cfg_attr(not(target_arch = "wasm32"), async_trait)]
impl CatalogStore for SqliteStore {
    async fn list_resource_types(&self, catalog_id: &str) -> anyhow::Result<Vec<ResourceType>> {
        let rows =
            sqlx::query("SELECT body FROM resource_types WHERE catalog_id = ? ORDER BY type_id")
                .bind(catalog_id)
                .fetch_all(&self.pool)
                .await?;

        rows.into_iter()
            .map(|r| {
                Ok(serde_json::from_str::<ResourceType>(
                    &r.get::<String, _>("body"),
                )?)
            })
            .collect()
    }

    async fn get_resource_type(
        &self,
        catalog_id: &str,
        type_id: &str,
    ) -> anyhow::Result<Option<ResourceType>> {
        let row =
            sqlx::query("SELECT body FROM resource_types WHERE catalog_id = ? AND type_id = ?")
                .bind(catalog_id)
                .bind(type_id)
                .fetch_optional(&self.pool)
                .await?;

        row.map(|r| {
            Ok(serde_json::from_str::<ResourceType>(
                &r.get::<String, _>("body"),
            )?)
        })
        .transpose()
    }

    async fn upsert_resource_type(&self, catalog_id: &str, rt: ResourceType) -> anyhow::Result<()> {
        sqlx::query(
            "INSERT INTO resource_types (catalog_id, type_id, body)
             VALUES (?, ?, ?)
             ON CONFLICT(catalog_id, type_id) DO UPDATE SET body = excluded.body",
        )
        .bind(catalog_id)
        .bind(&rt.id)
        .bind(serde_json::to_string(&rt)?)
        .execute(&self.pool)
        .await?;
        Ok(())
    }

    async fn delete_resource_type(&self, catalog_id: &str, type_id: &str) -> anyhow::Result<()> {
        sqlx::query("DELETE FROM resource_types WHERE catalog_id = ? AND type_id = ?")
            .bind(catalog_id)
            .bind(type_id)
            .execute(&self.pool)
            .await?;
        Ok(())
    }

    async fn list_resources(
        &self,
        catalog_id: &str,
        type_id: &str,
    ) -> anyhow::Result<Vec<Resource>> {
        let rows = sqlx::query(
            "SELECT type_id, resource_id, body FROM resources WHERE catalog_id = ? AND type_id = ? ORDER BY resource_id",
        )
        .bind(catalog_id)
        .bind(type_id)
        .fetch_all(&self.pool)
        .await?;

        rows.into_iter()
            .map(|r| {
                let row_type = r.get::<String, _>("type_id");
                let row_id = r.get::<String, _>("resource_id");
                Self::decode_resource_row(
                    &r.get::<String, _>("body"),
                    Some(&row_type),
                    Some(&row_id),
                )
            })
            .collect()
    }

    async fn get_resource(
        &self,
        catalog_id: &str,
        type_id: &str,
        resource_id: &str,
    ) -> anyhow::Result<Option<Resource>> {
        let row = sqlx::query(
            "SELECT type_id, resource_id, body FROM resources WHERE catalog_id = ? AND type_id = ? AND resource_id = ?",
        )
        .bind(catalog_id)
        .bind(type_id)
        .bind(resource_id)
        .fetch_optional(&self.pool)
        .await?;

        row.map(|r| {
            let row_type = r.get::<String, _>("type_id");
            let row_id = r.get::<String, _>("resource_id");
            Self::decode_resource_row(&r.get::<String, _>("body"), Some(&row_type), Some(&row_id))
        })
        .transpose()
    }

    async fn upsert_resource(&self, catalog_id: &str, resource: Resource) -> anyhow::Result<()> {
        let type_id = resource.resource_type.clone();
        let resource_id = resource.id.clone();

        sqlx::query(
            "INSERT INTO resources (catalog_id, type_id, resource_id, body)
             VALUES (?, ?, ?, ?)
             ON CONFLICT(catalog_id, type_id, resource_id) DO UPDATE SET body = excluded.body",
        )
        .bind(catalog_id)
        .bind(&type_id)
        .bind(&resource_id)
        .bind(serde_json::to_string(&resource)?)
        .execute(&self.pool)
        .await?;

        Ok(())
    }

    async fn delete_resource(
        &self,
        catalog_id: &str,
        type_id: &str,
        resource_id: &str,
    ) -> anyhow::Result<()> {
        sqlx::query(
            "DELETE FROM resources WHERE catalog_id = ? AND type_id = ? AND resource_id = ?",
        )
        .bind(catalog_id)
        .bind(type_id)
        .bind(resource_id)
        .execute(&self.pool)
        .await?;
        Ok(())
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use cataloga_core::{FieldDef, FieldType, Resource, ResourceType};
    use serde_json::Map;
    use std::collections::HashMap;

    #[tokio::test]
    async fn sqlite_roundtrip() {
        let store = SqliteStore::connect("sqlite::memory:").await.unwrap();

        let rt = ResourceType {
            id: "site".into(),
            title: "Site".into(),
            group: "infra".into(),
            description: String::new(),
            fields: vec![FieldDef {
                name: "city".into(),
                label: "City".into(),
                field_type: FieldType::String,
                enum_values: vec![],
            }],
            required_fields: vec![],
            list_columns: vec!["name".into()],
            form_layout: vec![],
            detail_sections: vec![],
            references: vec![],
            validation_rules: vec![],
        };
        store.upsert_resource_type("default", rt).await.unwrap();
        let list = store.list_resource_types("default").await.unwrap();
        assert_eq!(list.len(), 1);

        let resource = Resource {
            id: "tokyo".into(),
            resource_type: "site".into(),
            name: "Tokyo".into(),
            tags: HashMap::new(),
            spec: Map::new(),
            custom_fields: Map::new(),
            dependencies: Map::new(),
        };
        store.upsert_resource("default", resource).await.unwrap();
        let resources = store.list_resources("default", "site").await.unwrap();
        assert_eq!(resources.len(), 1);
        let resource = store
            .get_resource("default", "site", "tokyo")
            .await
            .unwrap()
            .unwrap();
        assert_eq!(resource.id, "tokyo");
        assert_eq!(resource.resource_type, "site");
    }
}
