use cataloga_core::{
    Resource, ResourceType, export_yaml, import_yaml, validate_resource_type, validate_resources,
};
use cataloga_store::CatalogStore;
use serde::Serialize;

#[derive(Debug, Clone, Serialize)]
pub struct ValidationIssue {
    pub severity: String,
    pub resource_type: String,
    pub resource_id: String,
    pub field: String,
    pub message: String,
}

#[derive(Debug, Clone, Serialize)]
pub struct ValidationResult {
    pub status: String,
    pub errors: Vec<ValidationIssue>,
    pub warnings: Vec<ValidationIssue>,
}

#[derive(Debug, Clone, Serialize)]
pub struct ImportPreviewResult {
    pub resource_types_to_create: Vec<String>,
    pub resource_types_to_update: Vec<String>,
    pub resources_to_create: Vec<String>,
    pub resources_to_update: Vec<String>,
    pub validation_errors: Vec<ValidationIssue>,
}

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
        let existing = self.store.list_resources(catalog_id, type_id).await?;
        if !existing.is_empty() {
            anyhow::bail!("resource type has existing resources and cannot be deleted");
        }
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
        let report = self.validation_result(catalog_id).await?;
        if report.status == "failed" {
            anyhow::bail!("catalog validation failed");
        }
        Ok(())
    }

    pub async fn validation_result(&self, catalog_id: &str) -> anyhow::Result<ValidationResult> {
        let types = self.store.list_resource_types(catalog_id).await?;
        let mut all_resources = Vec::new();
        for rt in &types {
            all_resources.extend(self.store.list_resources(catalog_id, &rt.id).await?);
        }
        Ok(build_validation_result(&types, &all_resources))
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
        let preview = self.import_catalog_preview(catalog_id, input).await?;
        if !preview.validation_errors.is_empty() {
            anyhow::bail!("import preview failed validation");
        }

        let (types, resources) = import_yaml(input)?;

        for rt in types {
            self.create_or_update_resource_type(catalog_id, rt).await?;
        }
        for resource in resources {
            self.create_or_update_resource(catalog_id, resource).await?;
        }

        Ok(())
    }

    pub async fn import_catalog_preview(
        &self,
        catalog_id: &str,
        input: &str,
    ) -> anyhow::Result<ImportPreviewResult> {
        let (types, resources) = import_yaml(input)?;
        let existing_types = self.store.list_resource_types(catalog_id).await?;

        let mut existing_type_ids = std::collections::HashSet::new();
        let mut existing_resource_ids = std::collections::HashSet::new();
        for t in &existing_types {
            existing_type_ids.insert(t.id.clone());
            let current = self.store.list_resources(catalog_id, &t.id).await?;
            for r in current {
                existing_resource_ids.insert(format!("{}/{}", r.metadata.resource_type, r.metadata.id));
            }
        }

        let mut resource_types_to_create = Vec::new();
        let mut resource_types_to_update = Vec::new();
        for rt in &types {
            if existing_type_ids.contains(&rt.id) {
                resource_types_to_update.push(rt.id.clone());
            } else {
                resource_types_to_create.push(rt.id.clone());
            }
        }

        let mut resources_to_create = Vec::new();
        let mut resources_to_update = Vec::new();
        for r in &resources {
            let key = format!("{}/{}", r.metadata.resource_type, r.metadata.id);
            if existing_resource_ids.contains(&key) {
                resources_to_update.push(key);
            } else {
                resources_to_create.push(key);
            }
        }

        let validation_errors = build_validation_result(&types, &resources).errors;
        Ok(ImportPreviewResult {
            resource_types_to_create,
            resource_types_to_update,
            resources_to_create,
            resources_to_update,
            validation_errors,
        })
    }
}

fn build_validation_result(types: &[ResourceType], resources: &[Resource]) -> ValidationResult {
    let mut errors = Vec::new();

    for rt in types {
        if let Err(e) = validate_resource_type(rt) {
            errors.push(ValidationIssue {
                severity: "error".to_string(),
                resource_type: rt.id.clone(),
                resource_id: String::new(),
                field: String::new(),
                message: e.to_string(),
            });
        }
    }

    if let Err(e) = validate_resources(types, resources) {
        errors.push(ValidationIssue {
            severity: "error".to_string(),
            resource_type: String::new(),
            resource_id: String::new(),
            field: String::new(),
            message: e.to_string(),
        });
    }

    ValidationResult {
        status: if errors.is_empty() {
            "ok".to_string()
        } else {
            "failed".to_string()
        },
        errors,
        warnings: Vec::new(),
    }
}
