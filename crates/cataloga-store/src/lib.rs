use async_trait::async_trait;
use cataloga_core::{Resource, ResourceType};
use std::sync::Arc;

#[cfg_attr(target_arch = "wasm32", async_trait(?Send))]
#[cfg_attr(not(target_arch = "wasm32"), async_trait)]
pub trait CatalogStore: Send + Sync {
    async fn list_resource_types(&self, catalog_id: &str) -> anyhow::Result<Vec<ResourceType>>;
    async fn get_resource_type(
        &self,
        catalog_id: &str,
        type_id: &str,
    ) -> anyhow::Result<Option<ResourceType>>;
    async fn upsert_resource_type(&self, catalog_id: &str, rt: ResourceType) -> anyhow::Result<()>;
    async fn delete_resource_type(&self, catalog_id: &str, type_id: &str) -> anyhow::Result<()>;

    async fn list_resources(
        &self,
        catalog_id: &str,
        type_id: &str,
    ) -> anyhow::Result<Vec<Resource>>;
    async fn get_resource(
        &self,
        catalog_id: &str,
        type_id: &str,
        resource_id: &str,
    ) -> anyhow::Result<Option<Resource>>;
    async fn upsert_resource(&self, catalog_id: &str, resource: Resource) -> anyhow::Result<()>;
    async fn delete_resource(
        &self,
        catalog_id: &str,
        type_id: &str,
        resource_id: &str,
    ) -> anyhow::Result<()>;
}

#[cfg_attr(target_arch = "wasm32", async_trait(?Send))]
#[cfg_attr(not(target_arch = "wasm32"), async_trait)]
impl<T> CatalogStore for Arc<T>
where
    T: CatalogStore + Send + Sync + ?Sized,
{
    async fn list_resource_types(&self, catalog_id: &str) -> anyhow::Result<Vec<ResourceType>> {
        (**self).list_resource_types(catalog_id).await
    }

    async fn get_resource_type(
        &self,
        catalog_id: &str,
        type_id: &str,
    ) -> anyhow::Result<Option<ResourceType>> {
        (**self).get_resource_type(catalog_id, type_id).await
    }

    async fn upsert_resource_type(&self, catalog_id: &str, rt: ResourceType) -> anyhow::Result<()> {
        (**self).upsert_resource_type(catalog_id, rt).await
    }

    async fn delete_resource_type(&self, catalog_id: &str, type_id: &str) -> anyhow::Result<()> {
        (**self).delete_resource_type(catalog_id, type_id).await
    }

    async fn list_resources(
        &self,
        catalog_id: &str,
        type_id: &str,
    ) -> anyhow::Result<Vec<Resource>> {
        (**self).list_resources(catalog_id, type_id).await
    }

    async fn get_resource(
        &self,
        catalog_id: &str,
        type_id: &str,
        resource_id: &str,
    ) -> anyhow::Result<Option<Resource>> {
        (**self)
            .get_resource(catalog_id, type_id, resource_id)
            .await
    }

    async fn upsert_resource(&self, catalog_id: &str, resource: Resource) -> anyhow::Result<()> {
        (**self).upsert_resource(catalog_id, resource).await
    }

    async fn delete_resource(
        &self,
        catalog_id: &str,
        type_id: &str,
        resource_id: &str,
    ) -> anyhow::Result<()> {
        (**self)
            .delete_resource(catalog_id, type_id, resource_id)
            .await
    }
}
