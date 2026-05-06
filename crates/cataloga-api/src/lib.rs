use cataloga_core::{
    Resource, ResourceType, export_yaml, import_yaml, validate_resource_type, validate_resources,
    validate_resources_detailed,
};
use cataloga_store::CatalogStore;
use serde::Serialize;
use std::collections::HashMap;

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

#[derive(Debug, Clone, Serialize)]
pub struct ResourceRef {
    pub resource_type: String,
    pub resource_id: String,
    pub name: String,
    pub field: String,
}

#[derive(Debug, Clone, Serialize)]
pub struct ResourceReferences {
    pub outgoing: Vec<ResourceRef>,
    pub incoming: Vec<ResourceRef>,
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

    pub async fn resource_references(
        &self,
        catalog_id: &str,
        type_id: &str,
        resource_id: &str,
    ) -> anyhow::Result<ResourceReferences> {
        let types = self.store.list_resource_types(catalog_id).await?;
        let mut by_type: HashMap<String, Vec<Resource>> = HashMap::new();
        for rt in &types {
            by_type.insert(
                rt.id.clone(),
                self.store.list_resources(catalog_id, &rt.id).await?,
            );
        }

        let current = by_type
            .get(type_id)
            .and_then(|items| items.iter().find(|r| r.metadata.id == resource_id))
            .ok_or_else(|| anyhow::anyhow!("resource not found"))?;

        let mut outgoing = Vec::new();
        let mut incoming = Vec::new();

        for rt in &types {
            let resources = by_type.get(&rt.id).cloned().unwrap_or_default();
            for resource in resources {
                for reference in &rt.references {
                    let Some(value) = resource.spec.get(&reference.field) else {
                        continue;
                    };
                    if reference.multiple {
                        let Some(items) = value.as_array() else {
                            continue;
                        };
                        for item in items {
                            let Some(target_id) = item.as_str() else {
                                continue;
                            };
                            if resource.metadata.resource_type == type_id
                                && resource.metadata.id == resource_id
                            {
                                if reference.target_type == type_id {
                                    if let Some(target) = by_type
                                        .get(type_id)
                                        .and_then(|x| x.iter().find(|r| r.metadata.id == target_id))
                                    {
                                        outgoing.push(ResourceRef {
                                            resource_type: type_id.to_string(),
                                            resource_id: target.metadata.id.clone(),
                                            name: target.metadata.name.clone(),
                                            field: reference.field.clone(),
                                        });
                                    }
                                } else if let Some(target) = by_type
                                    .get(&reference.target_type)
                                    .and_then(|x| x.iter().find(|r| r.metadata.id == target_id))
                                {
                                    outgoing.push(ResourceRef {
                                        resource_type: reference.target_type.clone(),
                                        resource_id: target.metadata.id.clone(),
                                        name: target.metadata.name.clone(),
                                        field: reference.field.clone(),
                                    });
                                }
                            }
                            if reference.target_type == type_id
                                && target_id == resource_id
                                && !(resource.metadata.resource_type == type_id
                                    && resource.metadata.id == resource_id)
                            {
                                incoming.push(ResourceRef {
                                    resource_type: resource.metadata.resource_type.clone(),
                                    resource_id: resource.metadata.id.clone(),
                                    name: resource.metadata.name.clone(),
                                    field: reference.field.clone(),
                                });
                            }
                        }
                    } else {
                        let Some(target_id) = value.as_str() else {
                            continue;
                        };
                        if resource.metadata.resource_type == type_id
                            && resource.metadata.id == resource_id
                        {
                            if reference.target_type == type_id {
                                if let Some(target) = by_type
                                    .get(type_id)
                                    .and_then(|x| x.iter().find(|r| r.metadata.id == target_id))
                                {
                                    outgoing.push(ResourceRef {
                                        resource_type: type_id.to_string(),
                                        resource_id: target.metadata.id.clone(),
                                        name: target.metadata.name.clone(),
                                        field: reference.field.clone(),
                                    });
                                }
                            } else if let Some(target) = by_type
                                .get(&reference.target_type)
                                .and_then(|x| x.iter().find(|r| r.metadata.id == target_id))
                            {
                                outgoing.push(ResourceRef {
                                    resource_type: reference.target_type.clone(),
                                    resource_id: target.metadata.id.clone(),
                                    name: target.metadata.name.clone(),
                                    field: reference.field.clone(),
                                });
                            }
                        }
                        if reference.target_type == type_id
                            && target_id == resource_id
                            && !(resource.metadata.resource_type == type_id
                                && resource.metadata.id == resource_id)
                        {
                            incoming.push(ResourceRef {
                                resource_type: resource.metadata.resource_type.clone(),
                                resource_id: resource.metadata.id.clone(),
                                name: resource.metadata.name.clone(),
                                field: reference.field.clone(),
                            });
                        }
                    }
                }
            }
        }

        let _ = current;
        Ok(ResourceReferences { outgoing, incoming })
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
                existing_resource_ids
                    .insert(format!("{}/{}", r.metadata.resource_type, r.metadata.id));
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

    for issue in validate_resources_detailed(types, resources) {
        errors.push(ValidationIssue {
            severity: "error".to_string(),
            resource_type: issue.resource_type,
            resource_id: issue.resource_id,
            field: issue.field,
            message: issue.message,
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

#[cfg(test)]
mod tests {
    use super::*;
    use async_trait::async_trait;
    use cataloga_core::{Metadata, ReferenceDef};
    use serde_json::json;
    use std::sync::{Arc, Mutex};

    #[derive(Default, Clone)]
    struct MemoryStore {
        types: Arc<Mutex<HashMap<String, ResourceType>>>,
        resources: Arc<Mutex<HashMap<(String, String), Resource>>>,
    }

    #[async_trait]
    impl CatalogStore for MemoryStore {
        async fn list_resource_types(
            &self,
            _catalog_id: &str,
        ) -> anyhow::Result<Vec<ResourceType>> {
            Ok(self.types.lock().unwrap().values().cloned().collect())
        }
        async fn get_resource_type(
            &self,
            _catalog_id: &str,
            type_id: &str,
        ) -> anyhow::Result<Option<ResourceType>> {
            Ok(self.types.lock().unwrap().get(type_id).cloned())
        }
        async fn upsert_resource_type(
            &self,
            _catalog_id: &str,
            rt: ResourceType,
        ) -> anyhow::Result<()> {
            self.types.lock().unwrap().insert(rt.id.clone(), rt);
            Ok(())
        }
        async fn delete_resource_type(
            &self,
            _catalog_id: &str,
            type_id: &str,
        ) -> anyhow::Result<()> {
            self.types.lock().unwrap().remove(type_id);
            Ok(())
        }
        async fn list_resources(
            &self,
            _catalog_id: &str,
            type_id: &str,
        ) -> anyhow::Result<Vec<Resource>> {
            Ok(self
                .resources
                .lock()
                .unwrap()
                .values()
                .filter(|r| r.metadata.resource_type == type_id)
                .cloned()
                .collect())
        }
        async fn get_resource(
            &self,
            _catalog_id: &str,
            type_id: &str,
            resource_id: &str,
        ) -> anyhow::Result<Option<Resource>> {
            Ok(self
                .resources
                .lock()
                .unwrap()
                .get(&(type_id.to_string(), resource_id.to_string()))
                .cloned())
        }
        async fn upsert_resource(
            &self,
            _catalog_id: &str,
            resource: Resource,
        ) -> anyhow::Result<()> {
            self.resources.lock().unwrap().insert(
                (
                    resource.metadata.resource_type.clone(),
                    resource.metadata.id.clone(),
                ),
                resource,
            );
            Ok(())
        }
        async fn delete_resource(
            &self,
            _catalog_id: &str,
            type_id: &str,
            resource_id: &str,
        ) -> anyhow::Result<()> {
            self.resources
                .lock()
                .unwrap()
                .remove(&(type_id.to_string(), resource_id.to_string()));
            Ok(())
        }
    }

    fn resource(
        t: &str,
        id: &str,
        name: &str,
        spec: serde_json::Map<String, serde_json::Value>,
    ) -> Resource {
        Resource {
            api_version: "cataloga.io/v1".to_string(),
            kind: "Resource".to_string(),
            metadata: Metadata {
                id: id.to_string(),
                resource_type: t.to_string(),
                name: name.to_string(),
                tags: HashMap::new(),
            },
            spec,
            custom_fields: serde_json::Map::new(),
            dependencies: serde_json::Map::new(),
        }
    }

    #[tokio::test]
    async fn resource_references_returns_outgoing_and_incoming() {
        let store = MemoryStore::default();
        let api = ApiService::new(store.clone());
        store
            .upsert_resource_type(
                "default",
                ResourceType {
                    id: "vm".into(),
                    title: "VM".into(),
                    group: String::new(),
                    description: String::new(),
                    fields: vec![],
                    required_fields: vec![],
                    list_columns: vec![],
                    form_layout: vec![],
                    detail_sections: vec![],
                    references: vec![ReferenceDef {
                        field: "primary_ip".into(),
                        target_type: "ip_address".into(),
                        multiple: false,
                    }],
                    validation_rules: vec![],
                },
            )
            .await
            .unwrap();
        store
            .upsert_resource_type(
                "default",
                ResourceType {
                    id: "ip_address".into(),
                    title: "IP".into(),
                    group: String::new(),
                    description: String::new(),
                    fields: vec![],
                    required_fields: vec![],
                    list_columns: vec![],
                    form_layout: vec![],
                    detail_sections: vec![],
                    references: vec![],
                    validation_rules: vec![],
                },
            )
            .await
            .unwrap();
        let mut vm_spec = serde_json::Map::new();
        vm_spec.insert("primary_ip".into(), json!("10.0.0.1"));
        store
            .upsert_resource("default", resource("vm", "vm1", "VM1", vm_spec))
            .await
            .unwrap();
        store
            .upsert_resource(
                "default",
                resource("ip_address", "10.0.0.1", "10.0.0.1", serde_json::Map::new()),
            )
            .await
            .unwrap();

        let refs = api
            .resource_references("default", "vm", "vm1")
            .await
            .unwrap();
        assert_eq!(refs.outgoing.len(), 1);
        assert_eq!(refs.incoming.len(), 0);

        let refs_ip = api
            .resource_references("default", "ip_address", "10.0.0.1")
            .await
            .unwrap();
        assert_eq!(refs_ip.outgoing.len(), 0);
        assert_eq!(refs_ip.incoming.len(), 1);
    }

    #[tokio::test]
    async fn resource_references_returns_empty_arrays_when_no_refs() {
        let store = MemoryStore::default();
        let api = ApiService::new(store.clone());
        store
            .upsert_resource_type(
                "default",
                ResourceType {
                    id: "site".into(),
                    title: "Site".into(),
                    group: String::new(),
                    description: String::new(),
                    fields: vec![],
                    required_fields: vec![],
                    list_columns: vec![],
                    form_layout: vec![],
                    detail_sections: vec![],
                    references: vec![],
                    validation_rules: vec![],
                },
            )
            .await
            .unwrap();
        store
            .upsert_resource(
                "default",
                resource("site", "tokyo", "Tokyo", serde_json::Map::new()),
            )
            .await
            .unwrap();
        let refs = api
            .resource_references("default", "site", "tokyo")
            .await
            .unwrap();
        assert!(refs.outgoing.is_empty());
        assert!(refs.incoming.is_empty());
    }
}
