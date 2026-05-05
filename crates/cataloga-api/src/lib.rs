use cataloga_core::{
    Resource, ResourceType, export_yaml, import_yaml, validate_resource_type, validate_resources,
};
use cataloga_store::CatalogStore;

pub struct ApiService<S: CatalogStore> {
    store: S,
}

impl<S: CatalogStore> ApiService<S> {
    pub fn new(store: S) -> Self {
        Self { store }
    }

    pub async fn list_resource_types(&self, catalog_id: &str) -> anyhow::Result<Vec<ResourceType>> {
        self.store.list_resource_types(catalog_id).await
    }

    pub async fn get_resource_type(
        &self,
        catalog_id: &str,
        type_id: &str,
    ) -> anyhow::Result<Option<ResourceType>> {
        self.store.get_resource_type(catalog_id, type_id).await
    }

    pub async fn create_or_update_resource_type(
        &self,
        catalog_id: &str,
        rt: ResourceType,
    ) -> anyhow::Result<()> {
        validate_resource_type(&rt)?;
        self.store.upsert_resource_type(catalog_id, rt).await
    }

    pub async fn delete_resource_type(
        &self,
        catalog_id: &str,
        type_id: &str,
    ) -> anyhow::Result<()> {
        self.store.delete_resource_type(catalog_id, type_id).await
    }

    pub async fn list_resources(
        &self,
        catalog_id: &str,
        type_id: &str,
    ) -> anyhow::Result<Vec<Resource>> {
        self.store.list_resources(catalog_id, type_id).await
    }

    pub async fn get_resource(
        &self,
        catalog_id: &str,
        type_id: &str,
        resource_id: &str,
    ) -> anyhow::Result<Option<Resource>> {
        self.store
            .get_resource(catalog_id, type_id, resource_id)
            .await
    }

    pub async fn create_or_update_resource(
        &self,
        catalog_id: &str,
        resource: Resource,
    ) -> anyhow::Result<()> {
        let mut all = self
            .store
            .list_resources(catalog_id, &resource.metadata.resource_type)
            .await?;
        all.push(resource.clone());
        let types = self.store.list_resource_types(catalog_id).await?;
        validate_resources(&types, &all)?;
        self.store.upsert_resource(catalog_id, resource).await
    }

    pub async fn delete_resource(
        &self,
        catalog_id: &str,
        type_id: &str,
        resource_id: &str,
    ) -> anyhow::Result<()> {
        self.store
            .delete_resource(catalog_id, type_id, resource_id)
            .await
    }

    pub async fn validate_catalog(&self, catalog_id: &str) -> anyhow::Result<()> {
        let types = self.store.list_resource_types(catalog_id).await?;
        let mut all_resources = Vec::new();
        for rt in &types {
            all_resources.extend(self.store.list_resources(catalog_id, &rt.id).await?);
        }
        validate_resources(&types, &all_resources)?;
        Ok(())
    }

    pub async fn export_catalog_yaml(&self, catalog_id: &str) -> anyhow::Result<String> {
        let types = self.store.list_resource_types(catalog_id).await?;
        let mut all_resources = Vec::new();
        for rt in &types {
            all_resources.extend(self.store.list_resources(catalog_id, &rt.id).await?);
        }
        export_yaml(&types, &all_resources)
    }

    pub async fn import_catalog_yaml(&self, catalog_id: &str, input: &str) -> anyhow::Result<()> {
        let (types, resources) = import_yaml(input)?;

        for rt in types {
            self.create_or_update_resource_type(catalog_id, rt).await?;
        }
        for resource in resources {
            self.create_or_update_resource(catalog_id, resource).await?;
        }

        Ok(())
    }
}
