use async_trait::async_trait;
use cataloga_core::{Resource, ResourceType};
use cataloga_store::CatalogStore;

#[cfg(target_arch = "wasm32")]
use worker::{D1Database, D1PreparedStatement};

#[cfg(target_arch = "wasm32")]
pub struct D1Store {
    db: D1Database,
}

#[cfg(target_arch = "wasm32")]
impl D1Store {
    pub fn new(db: D1Database) -> Self {
        Self { db }
    }

    async fn fetch_all<T: serde::de::DeserializeOwned>(
        &self,
        query: &str,
        params: Vec<worker::wasm_bindgen::JsValue>,
    ) -> anyhow::Result<Vec<T>> {
        let stmt = bind(self.db.prepare(query), params)?;
        let result = stmt.all().await?;
        Ok(result.results::<T>()?)
    }

    fn decode_resource_row(
        row: serde_json::Value,
        fallback_type_id: Option<&str>,
        fallback_resource_id: Option<&str>,
    ) -> anyhow::Result<Resource> {
        let type_id = row
            .get("type_id")
            .and_then(|x| x.as_str())
            .or(fallback_type_id)
            .unwrap_or_default();
        let resource_id = row
            .get("resource_id")
            .and_then(|x| x.as_str())
            .or(fallback_resource_id)
            .unwrap_or_default();
        let body = row
            .get("body")
            .and_then(|x| x.as_str())
            .ok_or_else(|| anyhow::anyhow!("missing body"))?;

        let mut json: serde_json::Value = serde_json::from_str(body)?;
        let obj = json
            .as_object_mut()
            .ok_or_else(|| anyhow::anyhow!("resource body must be a JSON object"))?;
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

#[cfg(target_arch = "wasm32")]
fn bind(
    stmt: D1PreparedStatement,
    params: Vec<worker::wasm_bindgen::JsValue>,
) -> anyhow::Result<D1PreparedStatement> {
    Ok(stmt.bind(&params)?)
}

#[cfg(target_arch = "wasm32")]
#[cfg_attr(target_arch = "wasm32", async_trait(?Send))]
#[cfg_attr(not(target_arch = "wasm32"), async_trait)]
impl CatalogStore for D1Store {
    async fn list_resource_types(&self, catalog_id: &str) -> anyhow::Result<Vec<ResourceType>> {
        let rows: Vec<serde_json::Value> = self
            .fetch_all(
                "SELECT body FROM resource_types WHERE catalog_id = ? ORDER BY type_id",
                vec![catalog_id.into()],
            )
            .await?;

        rows.into_iter()
            .map(|v| {
                let body = v
                    .get("body")
                    .and_then(|x| x.as_str())
                    .ok_or_else(|| anyhow::anyhow!("missing body"))?;
                Ok(serde_json::from_str::<ResourceType>(body)?)
            })
            .collect()
    }

    async fn get_resource_type(
        &self,
        catalog_id: &str,
        type_id: &str,
    ) -> anyhow::Result<Option<ResourceType>> {
        let mut items = self
            .fetch_all::<serde_json::Value>(
                "SELECT body FROM resource_types WHERE catalog_id = ? AND type_id = ?",
                vec![catalog_id.into(), type_id.into()],
            )
            .await?;
        let Some(row) = items.pop() else {
            return Ok(None);
        };
        let body = row
            .get("body")
            .and_then(|x| x.as_str())
            .ok_or_else(|| anyhow::anyhow!("missing body"))?;
        Ok(Some(serde_json::from_str::<ResourceType>(body)?))
    }

    async fn upsert_resource_type(&self, catalog_id: &str, rt: ResourceType) -> anyhow::Result<()> {
        let stmt = self.db.prepare(
            "INSERT INTO resource_types (catalog_id, type_id, body)
             VALUES (?, ?, ?)
             ON CONFLICT(catalog_id, type_id) DO UPDATE SET body = excluded.body",
        );
        bind(
            stmt,
            vec![
                catalog_id.into(),
                rt.id.clone().into(),
                serde_json::to_string(&rt)?.into(),
            ],
        )?
        .run()
        .await?;
        Ok(())
    }

    async fn delete_resource_type(&self, catalog_id: &str, type_id: &str) -> anyhow::Result<()> {
        bind(
            self.db
                .prepare("DELETE FROM resource_types WHERE catalog_id = ? AND type_id = ?"),
            vec![catalog_id.into(), type_id.into()],
        )?
        .run()
        .await?;
        Ok(())
    }

    async fn list_resources(
        &self,
        catalog_id: &str,
        type_id: &str,
    ) -> anyhow::Result<Vec<Resource>> {
        let rows: Vec<serde_json::Value> = self
            .fetch_all(
                "SELECT type_id, resource_id, body FROM resources WHERE catalog_id = ? AND type_id = ? ORDER BY resource_id",
                vec![catalog_id.into(), type_id.into()],
            )
            .await?;

        rows.into_iter()
            .map(|row| Self::decode_resource_row(row, Some(type_id), None))
            .collect()
    }

    async fn get_resource(
        &self,
        catalog_id: &str,
        type_id: &str,
        resource_id: &str,
    ) -> anyhow::Result<Option<Resource>> {
        let mut items = self
            .fetch_all::<serde_json::Value>(
                "SELECT body FROM resources WHERE catalog_id = ? AND type_id = ? AND resource_id = ?",
                vec![catalog_id.into(), type_id.into(), resource_id.into()],
            )
            .await?;
        let Some(row) = items.pop() else {
            return Ok(None);
        };
        Ok(Some(Self::decode_resource_row(
            row,
            Some(type_id),
            Some(resource_id),
        )?))
    }

    async fn upsert_resource(&self, catalog_id: &str, resource: Resource) -> anyhow::Result<()> {
        bind(
            self.db.prepare(
                "INSERT INTO resources (catalog_id, type_id, resource_id, body)
                 VALUES (?, ?, ?, ?)
                 ON CONFLICT(catalog_id, type_id, resource_id) DO UPDATE SET body = excluded.body",
            ),
            vec![
                catalog_id.into(),
                resource.resource_type.clone().into(),
                resource.id.clone().into(),
                serde_json::to_string(&resource)?.into(),
            ],
        )?
        .run()
        .await?;
        Ok(())
    }

    async fn delete_resource(
        &self,
        catalog_id: &str,
        type_id: &str,
        resource_id: &str,
    ) -> anyhow::Result<()> {
        bind(
            self.db.prepare(
                "DELETE FROM resources WHERE catalog_id = ? AND type_id = ? AND resource_id = ?",
            ),
            vec![catalog_id.into(), type_id.into(), resource_id.into()],
        )?
        .run()
        .await?;
        Ok(())
    }
}

#[cfg(not(target_arch = "wasm32"))]
pub struct D1Store;

#[cfg(not(target_arch = "wasm32"))]
impl D1Store {
    pub fn new<T>(_db: T) -> Self {
        Self
    }
}

#[cfg(not(target_arch = "wasm32"))]
#[async_trait]
impl CatalogStore for D1Store {
    async fn list_resource_types(&self, _catalog_id: &str) -> anyhow::Result<Vec<ResourceType>> {
        anyhow::bail!("D1 store is only available on wasm32")
    }

    async fn get_resource_type(
        &self,
        _catalog_id: &str,
        _type_id: &str,
    ) -> anyhow::Result<Option<ResourceType>> {
        anyhow::bail!("D1 store is only available on wasm32")
    }

    async fn upsert_resource_type(
        &self,
        _catalog_id: &str,
        _rt: ResourceType,
    ) -> anyhow::Result<()> {
        anyhow::bail!("D1 store is only available on wasm32")
    }

    async fn delete_resource_type(&self, _catalog_id: &str, _type_id: &str) -> anyhow::Result<()> {
        anyhow::bail!("D1 store is only available on wasm32")
    }

    async fn list_resources(
        &self,
        _catalog_id: &str,
        _type_id: &str,
    ) -> anyhow::Result<Vec<Resource>> {
        anyhow::bail!("D1 store is only available on wasm32")
    }

    async fn get_resource(
        &self,
        _catalog_id: &str,
        _type_id: &str,
        _resource_id: &str,
    ) -> anyhow::Result<Option<Resource>> {
        anyhow::bail!("D1 store is only available on wasm32")
    }

    async fn upsert_resource(&self, _catalog_id: &str, _resource: Resource) -> anyhow::Result<()> {
        anyhow::bail!("D1 store is only available on wasm32")
    }

    async fn delete_resource(
        &self,
        _catalog_id: &str,
        _type_id: &str,
        _resource_id: &str,
    ) -> anyhow::Result<()> {
        anyhow::bail!("D1 store is only available on wasm32")
    }
}
