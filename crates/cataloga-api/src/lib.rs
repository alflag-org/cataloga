use cataloga_core::{
    Resource, ResourceType, ResourceValidationIssue, export_yaml, import_yaml, is_active_reference,
    validate_resource_type_with_known_types, validate_resources_detailed,
};
use cataloga_store::CatalogStore;
use serde::Serialize;
use std::collections::{HashMap, HashSet};
use thiserror::Error;

#[derive(Debug, Clone, Copy, PartialEq, Eq, Hash)]
pub enum ApiMethod {
    Get,
    Post,
    Put,
    Delete,
}

#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub struct ApiRoute {
    pub method: ApiMethod,
    pub path: &'static str,
}

pub const CANONICAL_API_ROUTES: &[ApiRoute] = &[
    ApiRoute {
        method: ApiMethod::Get,
        path: "/api/health",
    },
    ApiRoute {
        method: ApiMethod::Get,
        path: "/api/resource-types",
    },
    ApiRoute {
        method: ApiMethod::Post,
        path: "/api/resource-types",
    },
    ApiRoute {
        method: ApiMethod::Get,
        path: "/api/resource-types/{type_id}",
    },
    ApiRoute {
        method: ApiMethod::Put,
        path: "/api/resource-types/{type_id}",
    },
    ApiRoute {
        method: ApiMethod::Delete,
        path: "/api/resource-types/{type_id}",
    },
    ApiRoute {
        method: ApiMethod::Get,
        path: "/api/resources/{type_id}",
    },
    ApiRoute {
        method: ApiMethod::Post,
        path: "/api/resources/{type_id}",
    },
    ApiRoute {
        method: ApiMethod::Get,
        path: "/api/resources/{type_id}/{resource_id}",
    },
    ApiRoute {
        method: ApiMethod::Put,
        path: "/api/resources/{type_id}/{resource_id}",
    },
    ApiRoute {
        method: ApiMethod::Delete,
        path: "/api/resources/{type_id}/{resource_id}",
    },
    ApiRoute {
        method: ApiMethod::Get,
        path: "/api/resources/{type_id}/{resource_id}/references",
    },
    ApiRoute {
        method: ApiMethod::Post,
        path: "/api/validate",
    },
    ApiRoute {
        method: ApiMethod::Get,
        path: "/api/validation",
    },
    ApiRoute {
        method: ApiMethod::Post,
        path: "/api/import",
    },
    ApiRoute {
        method: ApiMethod::Post,
        path: "/api/import/preview",
    },
    ApiRoute {
        method: ApiMethod::Post,
        path: "/api/import/apply",
    },
    ApiRoute {
        method: ApiMethod::Get,
        path: "/api/export",
    },
];

#[derive(Debug, Error)]
pub enum ApiError {
    #[error("{0}")]
    NotFound(String),
    #[error("{0}")]
    Validation(String),
    #[error("{0}")]
    Conflict(String),
    #[error("{0}")]
    BadRequest(String),
    #[error("{0}")]
    Internal(String),
}

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

type IssueKey = (String, String, String, String);

fn issue_key(issue: &ResourceValidationIssue) -> IssueKey {
    (
        issue.resource_type.clone(),
        issue.resource_id.clone(),
        issue.field.clone(),
        issue.message.clone(),
    )
}

fn issue_keys(issues: &[ResourceValidationIssue]) -> HashSet<IssueKey> {
    issues.iter().map(issue_key).collect()
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
        let existing_types = self.store.list_resource_types(catalog_id).await?;
        let all_types = merge_resource_types(existing_types, std::slice::from_ref(&rt));
        validate_resource_type_with_known_types(&rt, &all_types)
            .map_err(|e| ApiError::Validation(e.to_string()))?;
        self.store.upsert_resource_type(catalog_id, rt).await
    }

    pub async fn delete_resource_type(
        &self,
        catalog_id: &str,
        type_id: &str,
        delete_resources: bool,
    ) -> anyhow::Result<()> {
        let existing = self.store.list_resources(catalog_id, type_id).await?;
        if !existing.is_empty() && !delete_resources {
            return Err(ApiError::Conflict(
                "Resource Type has existing Resources and cannot be deleted".to_string(),
            )
            .into());
        }

        for resource in existing {
            self.store
                .delete_resource(catalog_id, type_id, &resource.id)
                .await?;
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
        let types = self.store.list_resource_types(catalog_id).await?;
        let mut all_resources = self.load_all_resources(catalog_id, &types).await?;
        let existing_issues = issue_keys(&validate_resources_detailed(&types, &all_resources));
        if let Some(existing_idx) = all_resources
            .iter()
            .position(|r| r.resource_type == resource.resource_type && r.id == resource.id)
        {
            all_resources[existing_idx] = resource.clone();
        } else {
            all_resources.push(resource.clone());
        }
        let issues = validate_resources_detailed(&types, &all_resources);
        if let Some(issue) = issues.iter().find(|issue| {
            issue.resource_type == resource.resource_type && issue.resource_id == resource.id
        }) {
            return Err(ApiError::Validation(issue.message.clone()).into());
        }
        if let Some(issue) = issues
            .iter()
            .find(|issue| !existing_issues.contains(&issue_key(issue)))
        {
            return Err(ApiError::Validation(issue.message.clone()).into());
        }
        self.store.upsert_resource(catalog_id, resource).await
    }

    pub async fn update_resource(
        &self,
        catalog_id: &str,
        current_type_id: &str,
        current_resource_id: &str,
        resource: Resource,
    ) -> anyhow::Result<()> {
        let types = self.store.list_resource_types(catalog_id).await?;
        let mut all_resources = self.load_all_resources(catalog_id, &types).await?;
        let existing_issues = issue_keys(&validate_resources_detailed(&types, &all_resources));
        let existing_idx = all_resources
            .iter()
            .position(|r| r.resource_type == current_type_id && r.id == current_resource_id)
            .ok_or_else(|| ApiError::NotFound("resource not found".to_string()))?;

        if all_resources.iter().enumerate().any(|(idx, r)| {
            idx != existing_idx && r.resource_type == resource.resource_type && r.id == resource.id
        }) {
            return Err(ApiError::Conflict(format!(
                "resource already exists: {}/{}",
                resource.resource_type, resource.id
            ))
            .into());
        }

        let id_changed =
            current_type_id != resource.resource_type || current_resource_id != resource.id;
        all_resources[existing_idx] = resource;

        let mut save_indices = HashSet::from([existing_idx]);
        if id_changed {
            let new_type_id = all_resources[existing_idx].resource_type.clone();
            let new_resource_id = all_resources[existing_idx].id.clone();
            save_indices.extend(rewrite_incoming_reference_ids(
                &types,
                &mut all_resources,
                current_type_id,
                current_resource_id,
                &new_type_id,
                &new_resource_id,
            ));
        }

        let issues = validate_resources_detailed(&types, &all_resources);
        if let Some(issue) = issues.iter().find(|issue| {
            issue.resource_type == all_resources[existing_idx].resource_type
                && issue.resource_id == all_resources[existing_idx].id
        }) {
            return Err(ApiError::Validation(issue.message.clone()).into());
        }
        if let Some(issue) = issues
            .iter()
            .find(|issue| !existing_issues.contains(&issue_key(issue)))
        {
            return Err(ApiError::Validation(issue.message.clone()).into());
        }

        for idx in save_indices {
            self.store
                .upsert_resource(catalog_id, all_resources[idx].clone())
                .await?;
        }
        if id_changed {
            self.store
                .delete_resource(catalog_id, current_type_id, current_resource_id)
                .await?;
        }
        Ok(())
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
        let mut resource_index: HashMap<(String, String), Resource> = HashMap::new();
        for rt in &types {
            let resources = self.store.list_resources(catalog_id, &rt.id).await?;
            for resource in &resources {
                resource_index.insert(
                    (resource.resource_type.clone(), resource.id.clone()),
                    resource.clone(),
                );
            }
            by_type.insert(rt.id.clone(), resources);
        }

        let _current = by_type
            .get(type_id)
            .and_then(|items| items.iter().find(|r| r.id == resource_id))
            .ok_or_else(|| ApiError::NotFound("resource not found".to_string()))?;

        let mut outgoing = Vec::new();
        let mut incoming = Vec::new();

        for rt in &types {
            let resources = by_type.get(&rt.id).map_or(&[][..], Vec::as_slice);
            for resource in resources {
                let is_current = resource.resource_type == type_id && resource.id == resource_id;
                for reference in rt
                    .references
                    .iter()
                    .filter(|reference| is_active_reference(rt, reference))
                {
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
                            if is_current
                                && let Some(target) = resource_index
                                    .get(&(reference.target_type.clone(), target_id.to_string()))
                            {
                                outgoing.push(ResourceRef {
                                    resource_type: reference.target_type.clone(),
                                    resource_id: target.id.clone(),
                                    name: target.name.clone(),
                                    field: reference.field.clone(),
                                });
                            }
                            if reference.target_type == type_id
                                && target_id == resource_id
                                && !(resource.resource_type == type_id
                                    && resource.id == resource_id)
                            {
                                incoming.push(ResourceRef {
                                    resource_type: resource.resource_type.clone(),
                                    resource_id: resource.id.clone(),
                                    name: resource.name.clone(),
                                    field: reference.field.clone(),
                                });
                            }
                        }
                    } else {
                        let Some(target_id) = value.as_str() else {
                            continue;
                        };
                        if is_current
                            && let Some(target) = resource_index
                                .get(&(reference.target_type.clone(), target_id.to_string()))
                        {
                            outgoing.push(ResourceRef {
                                resource_type: reference.target_type.clone(),
                                resource_id: target.id.clone(),
                                name: target.name.clone(),
                                field: reference.field.clone(),
                            });
                        }
                        if reference.target_type == type_id
                            && target_id == resource_id
                            && !(resource.resource_type == type_id && resource.id == resource_id)
                        {
                            incoming.push(ResourceRef {
                                resource_type: resource.resource_type.clone(),
                                resource_id: resource.id.clone(),
                                name: resource.name.clone(),
                                field: reference.field.clone(),
                            });
                        }
                    }
                }
            }
        }

        Ok(ResourceReferences { outgoing, incoming })
    }

    pub async fn validate_catalog(&self, catalog_id: &str) -> anyhow::Result<()> {
        let report = self.validation_result(catalog_id).await?;
        if report.status == "failed" {
            return Err(ApiError::Validation("catalog validation failed".to_string()).into());
        }
        Ok(())
    }

    pub async fn validation_result(&self, catalog_id: &str) -> anyhow::Result<ValidationResult> {
        let types = self.store.list_resource_types(catalog_id).await?;
        let all_resources = self.load_all_resources(catalog_id, &types).await?;
        Ok(build_validation_result(&types, &all_resources))
    }

    pub async fn export_catalog_yaml(&self, catalog_id: &str) -> anyhow::Result<String> {
        let types = self.store.list_resource_types(catalog_id).await?;
        let all_resources = self.load_all_resources(catalog_id, &types).await?;
        export_yaml(&types, &all_resources)
    }

    pub async fn import_catalog_yaml(&self, catalog_id: &str, input: &str) -> anyhow::Result<()> {
        let preview = self.import_catalog_preview(catalog_id, input).await?;
        if !preview.validation_errors.is_empty() {
            return Err(ApiError::Validation(format_import_validation_errors(
                &preview.validation_errors,
            ))
            .into());
        }

        let (imported_types, imported_resources) = parse_import_yaml(input)?;

        for rt in imported_types {
            self.store.upsert_resource_type(catalog_id, rt).await?;
        }
        for resource in imported_resources {
            self.store.upsert_resource(catalog_id, resource).await?;
        }

        Ok(())
    }

    pub async fn import_catalog_preview(
        &self,
        catalog_id: &str,
        input: &str,
    ) -> anyhow::Result<ImportPreviewResult> {
        let (imported_types, imported_resources) = parse_import_yaml(input)?;
        let existing_types = self.store.list_resource_types(catalog_id).await?;

        let mut existing_type_ids = std::collections::HashSet::new();
        let mut existing_resource_ids = std::collections::HashSet::new();
        for t in &existing_types {
            existing_type_ids.insert(t.id.clone());
            let current = self.store.list_resources(catalog_id, &t.id).await?;
            for r in current {
                existing_resource_ids.insert(format!("{}/{}", r.resource_type, r.id));
            }
        }

        let mut resource_types_to_create = Vec::new();
        let mut resource_types_to_update = Vec::new();
        for rt in &imported_types {
            if existing_type_ids.contains(&rt.id) {
                resource_types_to_update.push(rt.id.clone());
            } else {
                resource_types_to_create.push(rt.id.clone());
            }
        }

        let mut resources_to_create = Vec::new();
        let mut resources_to_update = Vec::new();
        for r in &imported_resources {
            let key = format!("{}/{}", r.resource_type, r.id);
            if existing_resource_ids.contains(&key) {
                resources_to_update.push(key);
            } else {
                resources_to_create.push(key);
            }
        }

        let merged_types = merge_resource_types(existing_types, &imported_types);
        let existing_resources = self.load_all_resources(catalog_id, &merged_types).await?;
        let merged_resources = merge_resources(existing_resources, &imported_resources);
        let validation_errors = build_validation_result(&merged_types, &merged_resources).errors;
        Ok(ImportPreviewResult {
            resource_types_to_create,
            resource_types_to_update,
            resources_to_create,
            resources_to_update,
            validation_errors,
        })
    }

    async fn load_all_resources(
        &self,
        catalog_id: &str,
        types: &[ResourceType],
    ) -> anyhow::Result<Vec<Resource>> {
        let mut all_resources = Vec::new();
        for rt in types {
            all_resources.extend(self.store.list_resources(catalog_id, &rt.id).await?);
        }
        Ok(all_resources)
    }
}

fn parse_import_yaml(input: &str) -> anyhow::Result<(Vec<ResourceType>, Vec<Resource>)> {
    import_yaml(input).map_err(|e| {
        ApiError::BadRequest(format!(
            "invalid Import YAML format: {e}. expected top-level keys `version`, `resource_types`, and `resources`; `version` must be 1, and each Resource must include `id`, `type`, `name`, and `spec`"
        ))
        .into()
    })
}

fn format_import_validation_errors(errors: &[ValidationIssue]) -> String {
    let shown = errors
        .iter()
        .take(5)
        .map(format_import_validation_issue)
        .collect::<Vec<_>>();
    let remaining = errors.len().saturating_sub(shown.len());
    let suffix = if remaining == 0 {
        String::new()
    } else {
        format!("; and {remaining} more")
    };

    format!(
        "Import validation failed with {} error(s): {}{}",
        errors.len(),
        shown.join("; "),
        suffix
    )
}

fn format_import_validation_issue(issue: &ValidationIssue) -> String {
    let mut location = Vec::new();
    if !issue.resource_type.is_empty() {
        location.push(format!("Resource Type `{}`", issue.resource_type));
    }
    if !issue.resource_id.is_empty() {
        location.push(format!("Resource `{}`", issue.resource_id));
    }
    if !issue.field.is_empty() {
        location.push(format!("Field `{}`", issue.field));
    }

    if location.is_empty() {
        issue.message.clone()
    } else {
        format!("{}: {}", location.join(", "), issue.message)
    }
}

fn build_validation_result(types: &[ResourceType], resources: &[Resource]) -> ValidationResult {
    let mut errors = Vec::new();

    for rt in types {
        if let Err(e) = validate_resource_type_with_known_types(rt, types) {
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

fn merge_resource_types(
    existing: Vec<ResourceType>,
    imported: &[ResourceType],
) -> Vec<ResourceType> {
    let mut merged = existing;
    for rt in imported {
        if let Some(idx) = merged.iter().position(|current| current.id == rt.id) {
            merged[idx] = rt.clone();
        } else {
            merged.push(rt.clone());
        }
    }
    merged
}

fn merge_resources(existing: Vec<Resource>, imported: &[Resource]) -> Vec<Resource> {
    let mut merged = existing;
    for resource in imported {
        if let Some(idx) = merged.iter().position(|current| {
            current.resource_type == resource.resource_type && current.id == resource.id
        }) {
            merged[idx] = resource.clone();
        } else {
            merged.push(resource.clone());
        }
    }
    merged
}

fn rewrite_incoming_reference_ids(
    types: &[ResourceType],
    resources: &mut [Resource],
    old_type_id: &str,
    old_resource_id: &str,
    new_type_id: &str,
    new_resource_id: &str,
) -> HashSet<usize> {
    if old_type_id != new_type_id {
        return HashSet::new();
    }

    let type_by_id: HashMap<&str, &ResourceType> =
        types.iter().map(|rt| (rt.id.as_str(), rt)).collect();
    let mut changed = HashSet::new();

    for (idx, resource) in resources.iter_mut().enumerate() {
        let Some(rt) = type_by_id.get(resource.resource_type.as_str()) else {
            continue;
        };
        let mut resource_changed = false;

        for reference in rt
            .references
            .iter()
            .filter(|reference| reference.target_type == old_type_id)
            .filter(|reference| is_active_reference(rt, reference))
        {
            if reference.multiple {
                let Some(items) = resource
                    .spec
                    .get_mut(&reference.field)
                    .and_then(|value| value.as_array_mut())
                else {
                    continue;
                };
                for item in items {
                    if item.as_str() == Some(old_resource_id) {
                        *item = serde_json::Value::String(new_resource_id.to_string());
                        resource_changed = true;
                    }
                }
            } else if resource
                .spec
                .get(&reference.field)
                .and_then(|value| value.as_str())
                == Some(old_resource_id)
            {
                resource.spec.insert(
                    reference.field.clone(),
                    serde_json::Value::String(new_resource_id.to_string()),
                );
                resource_changed = true;
            }
        }

        if resource_changed {
            changed.insert(idx);
        }
    }

    changed
}

#[cfg(test)]
mod tests {
    use super::*;
    use async_trait::async_trait;
    use cataloga_core::{FieldDef, FieldType, ReferenceDef, ValidationRule, ValidationRuleType};
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
                .filter(|r| r.resource_type == type_id)
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
                (resource.resource_type.clone(), resource.id.clone()),
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
            id: id.to_string(),
            resource_type: t.to_string(),
            name: name.to_string(),
            tags: HashMap::new(),
            spec,
            custom_fields: serde_json::Map::new(),
            dependencies: serde_json::Map::new(),
        }
    }

    fn resource_type(id: &str, title: &str) -> ResourceType {
        ResourceType {
            id: id.to_string(),
            title: title.to_string(),
            group: String::new(),
            description: String::new(),
            fields: vec![],
            required_fields: vec![],
            list_columns: vec![],
            form_layout: vec![],
            detail_sections: vec![],
            references: vec![],
            validation_rules: vec![],
        }
    }

    #[tokio::test]
    async fn delete_resource_type_rejects_existing_resources_without_delete_resources() {
        let store = MemoryStore::default();
        let api = ApiService::new(store.clone());
        store
            .upsert_resource_type("default", resource_type("site", "Site"))
            .await
            .unwrap();
        store
            .upsert_resource(
                "default",
                resource("site", "tokyo", "Tokyo", serde_json::Map::new()),
            )
            .await
            .unwrap();

        let err = api
            .delete_resource_type("default", "site", false)
            .await
            .expect_err("expected conflict");
        let api_err = err.downcast_ref::<ApiError>().expect("api error");
        match api_err {
            ApiError::Conflict(msg) => {
                assert!(msg.contains("Resource Type has existing Resources"))
            }
            other => panic!("unexpected error: {other:?}"),
        }

        assert!(
            store
                .get_resource_type("default", "site")
                .await
                .unwrap()
                .is_some()
        );
        assert!(
            store
                .get_resource("default", "site", "tokyo")
                .await
                .unwrap()
                .is_some()
        );
    }

    #[tokio::test]
    async fn delete_resource_type_removes_empty_type_without_delete_resources() {
        let store = MemoryStore::default();
        let api = ApiService::new(store.clone());
        store
            .upsert_resource_type("default", resource_type("site", "Site"))
            .await
            .unwrap();

        api.delete_resource_type("default", "site", false)
            .await
            .unwrap();

        assert!(
            store
                .get_resource_type("default", "site")
                .await
                .unwrap()
                .is_none()
        );
    }

    #[tokio::test]
    async fn delete_resource_type_with_delete_resources_removes_resources_and_type() {
        let store = MemoryStore::default();
        let api = ApiService::new(store.clone());
        store
            .upsert_resource_type("default", resource_type("site", "Site"))
            .await
            .unwrap();
        store
            .upsert_resource_type("default", resource_type("device", "Device"))
            .await
            .unwrap();
        for id in ["tokyo", "osaka"] {
            store
                .upsert_resource("default", resource("site", id, id, serde_json::Map::new()))
                .await
                .unwrap();
        }
        store
            .upsert_resource(
                "default",
                resource("device", "router-1", "Router 1", serde_json::Map::new()),
            )
            .await
            .unwrap();

        api.delete_resource_type("default", "site", true)
            .await
            .unwrap();

        assert!(
            store
                .get_resource_type("default", "site")
                .await
                .unwrap()
                .is_none()
        );
        assert!(
            store
                .list_resources("default", "site")
                .await
                .unwrap()
                .is_empty()
        );
        assert!(
            store
                .get_resource_type("default", "device")
                .await
                .unwrap()
                .is_some()
        );
        assert!(
            store
                .get_resource("default", "device", "router-1")
                .await
                .unwrap()
                .is_some()
        );
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
                    fields: vec![FieldDef {
                        name: "primary_ip".into(),
                        label: "Primary IP".into(),
                        field_type: FieldType::Reference,
                        enum_values: vec![],
                    }],
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

    #[tokio::test]
    async fn create_or_update_resource_allows_updating_same_id() {
        let store = MemoryStore::default();
        let api = ApiService::new(store.clone());
        store
            .upsert_resource_type(
                "default",
                ResourceType {
                    id: "device".into(),
                    title: "Device".into(),
                    group: String::new(),
                    description: String::new(),
                    fields: vec![FieldDef {
                        name: "description".into(),
                        label: "Description".into(),
                        field_type: FieldType::String,
                        enum_values: vec![],
                    }],
                    required_fields: vec![],
                    list_columns: vec!["name".into()],
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
                resource("device", "punira", "Punira", serde_json::Map::new()),
            )
            .await
            .unwrap();

        let mut updated_spec = serde_json::Map::new();
        updated_spec.insert("description".into(), json!("updated"));
        api.create_or_update_resource(
            "default",
            resource("device", "punira", "Punira Updated", updated_spec),
        )
        .await
        .unwrap();

        let saved = store
            .get_resource("default", "device", "punira")
            .await
            .unwrap()
            .unwrap();
        assert_eq!(saved.name, "Punira Updated");
        assert_eq!(
            saved.spec.get("description").and_then(|v| v.as_str()),
            Some("updated")
        );
    }

    #[tokio::test]
    async fn update_resource_allows_changing_id() {
        let store = MemoryStore::default();
        let api = ApiService::new(store.clone());
        store
            .upsert_resource_type(
                "default",
                ResourceType {
                    id: "device".into(),
                    title: "Device".into(),
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
                resource("device", "old-id", "Old", serde_json::Map::new()),
            )
            .await
            .unwrap();

        api.update_resource(
            "default",
            "device",
            "old-id",
            resource("device", "new-id", "New", serde_json::Map::new()),
        )
        .await
        .unwrap();

        assert!(
            store
                .get_resource("default", "device", "old-id")
                .await
                .unwrap()
                .is_none()
        );
        let saved = store
            .get_resource("default", "device", "new-id")
            .await
            .unwrap()
            .unwrap();
        assert_eq!(saved.name, "New");
    }

    #[tokio::test]
    async fn update_resource_rejects_existing_target_id() {
        let store = MemoryStore::default();
        let api = ApiService::new(store.clone());
        store
            .upsert_resource_type(
                "default",
                ResourceType {
                    id: "device".into(),
                    title: "Device".into(),
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
                resource("device", "old-id", "Old", serde_json::Map::new()),
            )
            .await
            .unwrap();
        store
            .upsert_resource(
                "default",
                resource("device", "new-id", "Existing", serde_json::Map::new()),
            )
            .await
            .unwrap();

        let err = api
            .update_resource(
                "default",
                "device",
                "old-id",
                resource("device", "new-id", "New", serde_json::Map::new()),
            )
            .await
            .expect_err("expected conflict");
        let api_err = err.downcast_ref::<ApiError>().expect("api error");
        match api_err {
            ApiError::Conflict(msg) => assert!(msg.contains("resource already exists")),
            other => panic!("unexpected error: {other:?}"),
        }
    }

    #[tokio::test]
    async fn update_resource_rewrites_incoming_references() {
        let store = MemoryStore::default();
        let api = ApiService::new(store.clone());

        for rt in [
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
            ResourceType {
                id: "network".into(),
                title: "Network".into(),
                group: String::new(),
                description: String::new(),
                fields: vec![
                    FieldDef {
                        name: "site".into(),
                        label: "Site".into(),
                        field_type: FieldType::Reference,
                        enum_values: vec![],
                    },
                    FieldDef {
                        name: "backup_sites".into(),
                        label: "Backup Sites".into(),
                        field_type: FieldType::ReferenceArray,
                        enum_values: vec![],
                    },
                ],
                required_fields: vec![],
                list_columns: vec![],
                form_layout: vec![],
                detail_sections: vec![],
                references: vec![
                    ReferenceDef {
                        field: "site".into(),
                        target_type: "site".into(),
                        multiple: false,
                    },
                    ReferenceDef {
                        field: "backup_sites".into(),
                        target_type: "site".into(),
                        multiple: true,
                    },
                ],
                validation_rules: vec![],
            },
        ] {
            store.upsert_resource_type("default", rt).await.unwrap();
        }

        store
            .upsert_resource(
                "default",
                resource("site", "old-site", "Old Site", serde_json::Map::new()),
            )
            .await
            .unwrap();
        store
            .upsert_resource(
                "default",
                resource("site", "other-site", "Other Site", serde_json::Map::new()),
            )
            .await
            .unwrap();
        let mut network_spec = serde_json::Map::new();
        network_spec.insert("site".into(), json!("old-site"));
        network_spec.insert("backup_sites".into(), json!(["old-site", "other-site"]));
        store
            .upsert_resource(
                "default",
                resource("network", "network-1", "Network 1", network_spec),
            )
            .await
            .unwrap();

        api.update_resource(
            "default",
            "site",
            "old-site",
            resource("site", "new-site", "New Site", serde_json::Map::new()),
        )
        .await
        .unwrap();

        let network = store
            .get_resource("default", "network", "network-1")
            .await
            .unwrap()
            .unwrap();
        assert_eq!(network.spec.get("site"), Some(&json!("new-site")));
        assert_eq!(
            network.spec.get("backup_sites"),
            Some(&json!(["new-site", "other-site"]))
        );
    }

    #[tokio::test]
    async fn update_resource_returns_not_found_for_missing_current_id() {
        let store = MemoryStore::default();
        let api = ApiService::new(store.clone());
        store
            .upsert_resource_type(
                "default",
                ResourceType {
                    id: "device".into(),
                    title: "Device".into(),
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

        let err = api
            .update_resource(
                "default",
                "device",
                "missing-id",
                resource("device", "new-id", "New", serde_json::Map::new()),
            )
            .await
            .expect_err("expected not found");
        let api_err = err.downcast_ref::<ApiError>().expect("api error");
        match api_err {
            ApiError::NotFound(msg) => assert_eq!(msg, "resource not found"),
            other => panic!("unexpected error: {other:?}"),
        }
    }

    #[tokio::test]
    async fn update_resource_validation_failure_keeps_existing_resources() {
        let store = MemoryStore::default();
        let api = ApiService::new(store.clone());

        for rt in [
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
            ResourceType {
                id: "network".into(),
                title: "Network".into(),
                group: String::new(),
                description: String::new(),
                fields: vec![FieldDef {
                    name: "site".into(),
                    label: "Site".into(),
                    field_type: FieldType::Reference,
                    enum_values: vec![],
                }],
                required_fields: vec![],
                list_columns: vec![],
                form_layout: vec![],
                detail_sections: vec![],
                references: vec![ReferenceDef {
                    field: "site".into(),
                    target_type: "site".into(),
                    multiple: false,
                }],
                validation_rules: vec![],
            },
        ] {
            store.upsert_resource_type("default", rt).await.unwrap();
        }

        store
            .upsert_resource(
                "default",
                resource("site", "old-site", "Old Site", serde_json::Map::new()),
            )
            .await
            .unwrap();
        let mut network_spec = serde_json::Map::new();
        network_spec.insert("site".into(), json!("old-site"));
        store
            .upsert_resource(
                "default",
                resource("network", "network-1", "Network 1", network_spec),
            )
            .await
            .unwrap();

        let err = api
            .update_resource(
                "default",
                "site",
                "old-site",
                resource("site", "", "New Site", serde_json::Map::new()),
            )
            .await
            .expect_err("expected validation error");
        let api_err = err.downcast_ref::<ApiError>().expect("api error");
        match api_err {
            ApiError::Validation(msg) => assert!(msg.contains("missing required resource fields")),
            other => panic!("unexpected error: {other:?}"),
        }

        assert!(
            store
                .get_resource("default", "site", "old-site")
                .await
                .unwrap()
                .is_some()
        );
        assert!(
            store
                .get_resource("default", "site", "")
                .await
                .unwrap()
                .is_none()
        );
        let network = store
            .get_resource("default", "network", "network-1")
            .await
            .unwrap()
            .unwrap();
        assert_eq!(network.spec.get("site"), Some(&json!("old-site")));
    }

    #[tokio::test]
    async fn import_preview_returns_bad_request_for_invalid_yaml_shape() {
        let store = MemoryStore::default();
        let api = ApiService::new(store);
        let invalid = r#"
version: 1
resource_types: []
resources:
  - type: provider
    name: ONPREM
    spec:
      status: active
"#;

        let err = api
            .import_catalog_preview("default", invalid)
            .await
            .expect_err("expected bad request");
        let api_err = err.downcast_ref::<ApiError>().expect("api error");
        match api_err {
            ApiError::BadRequest(msg) => {
                assert!(msg.contains("invalid Import YAML format"));
                assert!(msg.contains("line "));
                assert!(msg.contains("column "));
                assert!(msg.contains("resources[0]"));
                assert!(msg.contains("id"));
            }
            other => panic!("unexpected error: {other:?}"),
        }
    }

    #[tokio::test]
    async fn import_apply_reports_validation_error_locations() {
        let store = MemoryStore::default();
        let api = ApiService::new(store);
        let invalid = r#"
version: 1
resource_types: []
resources:
  - id: oci
    type: provider
    name: OCI
    spec:
      status: active
"#;

        let err = api
            .import_catalog_yaml("default", invalid)
            .await
            .expect_err("expected validation error");
        let api_err = err.downcast_ref::<ApiError>().expect("api error");
        match api_err {
            ApiError::Validation(msg) => {
                assert!(msg.contains("Import validation failed"));
                assert!(msg.contains("Resource Type `provider`"));
                assert!(msg.contains("Resource `oci`"));
                assert!(msg.contains("unknown resource type"));
            }
            other => panic!("unexpected error: {other:?}"),
        }
    }

    #[tokio::test]
    async fn create_or_update_resource_validates_cross_type_references_using_whole_catalog() {
        let store = MemoryStore::default();
        let api = ApiService::new(store.clone());

        for rt in [
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
            ResourceType {
                id: "zone".into(),
                title: "Zone".into(),
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
            ResourceType {
                id: "network".into(),
                title: "Network".into(),
                group: String::new(),
                description: String::new(),
                fields: vec![
                    FieldDef {
                        name: "site".into(),
                        label: "Site".into(),
                        field_type: FieldType::Reference,
                        enum_values: vec![],
                    },
                    FieldDef {
                        name: "zone".into(),
                        label: "Zone".into(),
                        field_type: FieldType::Reference,
                        enum_values: vec![],
                    },
                ],
                required_fields: vec![],
                list_columns: vec![],
                form_layout: vec![],
                detail_sections: vec![],
                references: vec![
                    ReferenceDef {
                        field: "site".into(),
                        target_type: "site".into(),
                        multiple: false,
                    },
                    ReferenceDef {
                        field: "zone".into(),
                        target_type: "zone".into(),
                        multiple: false,
                    },
                ],
                validation_rules: vec![],
            },
        ] {
            store.upsert_resource_type("default", rt).await.unwrap();
        }

        store
            .upsert_resource(
                "default",
                resource("site", "ONP-JP-KNG-01", "Site 1", serde_json::Map::new()),
            )
            .await
            .unwrap();
        store
            .upsert_resource(
                "default",
                resource("zone", "client", "Client Zone", serde_json::Map::new()),
            )
            .await
            .unwrap();

        let mut spec = serde_json::Map::new();
        spec.insert("site".into(), json!("ONP-JP-KNG-01"));
        spec.insert("zone".into(), json!("client"));
        api.create_or_update_resource(
            "default",
            resource("network", "ONP-JP-KNG-01-CLIENT-V100", "Network 1", spec),
        )
        .await
        .unwrap();
    }

    #[tokio::test]
    async fn create_or_update_resource_fails_when_reference_target_missing() {
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
            .upsert_resource_type(
                "default",
                ResourceType {
                    id: "network".into(),
                    title: "Network".into(),
                    group: String::new(),
                    description: String::new(),
                    fields: vec![FieldDef {
                        name: "site".into(),
                        label: "Site".into(),
                        field_type: FieldType::Reference,
                        enum_values: vec![],
                    }],
                    required_fields: vec![],
                    list_columns: vec![],
                    form_layout: vec![],
                    detail_sections: vec![],
                    references: vec![ReferenceDef {
                        field: "site".into(),
                        target_type: "site".into(),
                        multiple: false,
                    }],
                    validation_rules: vec![],
                },
            )
            .await
            .unwrap();

        let mut spec = serde_json::Map::new();
        spec.insert("site".into(), json!("UNKNOWN"));
        let err = api
            .create_or_update_resource("default", resource("network", "net1", "Network 1", spec))
            .await
            .expect_err("expected validation error");
        let api_err = err.downcast_ref::<ApiError>().expect("api error");
        match api_err {
            ApiError::Validation(msg) => assert!(msg.contains("references missing site: UNKNOWN")),
            other => panic!("unexpected error: {other:?}"),
        }
    }

    #[tokio::test]
    async fn create_or_update_resource_allows_unrelated_existing_validation_errors() {
        let store = MemoryStore::default();
        let api = ApiService::new(store.clone());

        for rt in [
            ResourceType {
                id: "zone".into(),
                title: "Zone".into(),
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
            ResourceType {
                id: "network".into(),
                title: "Network".into(),
                group: String::new(),
                description: String::new(),
                fields: vec![FieldDef {
                    name: "zone".into(),
                    label: "Zone".into(),
                    field_type: FieldType::Reference,
                    enum_values: vec![],
                }],
                required_fields: vec![],
                list_columns: vec![],
                form_layout: vec![],
                detail_sections: vec![],
                references: vec![ReferenceDef {
                    field: "zone".into(),
                    target_type: "zone".into(),
                    multiple: false,
                }],
                validation_rules: vec![],
            },
            ResourceType {
                id: "ip_reservation".into(),
                title: "IP Reservation".into(),
                group: String::new(),
                description: String::new(),
                fields: vec![FieldDef {
                    name: "address".into(),
                    label: "Address".into(),
                    field_type: FieldType::Ip,
                    enum_values: vec![],
                }],
                required_fields: vec![],
                list_columns: vec![],
                form_layout: vec![],
                detail_sections: vec![],
                references: vec![],
                validation_rules: vec![],
            },
        ] {
            store.upsert_resource_type("default", rt).await.unwrap();
        }

        let mut broken_spec = serde_json::Map::new();
        broken_spec.insert("zone".into(), json!("missing-zone"));
        store
            .upsert_resource(
                "default",
                resource("network", "net1", "Network 1", broken_spec),
            )
            .await
            .unwrap();

        let mut spec = serde_json::Map::new();
        spec.insert("address".into(), json!("10.10.10.242"));
        api.create_or_update_resource(
            "default",
            resource(
                "ip_reservation",
                "ip-10.10.10.242",
                "10.10.10.242 updated",
                spec,
            ),
        )
        .await
        .unwrap();
    }

    #[tokio::test]
    async fn create_or_update_resource_ignores_stale_reference_definition_for_non_reference_field()
    {
        let store = MemoryStore::default();
        let api = ApiService::new(store.clone());
        store
            .upsert_resource_type(
                "default",
                ResourceType {
                    id: "ip_reservation".into(),
                    title: "IP Reservation".into(),
                    group: String::new(),
                    description: String::new(),
                    fields: vec![FieldDef {
                        name: "zone".into(),
                        label: "Zone".into(),
                        field_type: FieldType::String,
                        enum_values: vec![],
                    }],
                    required_fields: vec![],
                    list_columns: vec![],
                    form_layout: vec![],
                    detail_sections: vec![],
                    references: vec![ReferenceDef {
                        field: "zone".into(),
                        target_type: "zone".into(),
                        multiple: false,
                    }],
                    validation_rules: vec![],
                },
            )
            .await
            .unwrap();

        let mut spec = serde_json::Map::new();
        spec.insert("zone".into(), json!("client"));

        api.create_or_update_resource(
            "default",
            resource("ip_reservation", "ip-10.10.10.242", "10.10.10.242", spec),
        )
        .await
        .unwrap();
    }

    #[tokio::test]
    async fn create_or_update_resource_rejects_new_global_validation_errors() {
        let store = MemoryStore::default();
        let api = ApiService::new(store.clone());

        store
            .upsert_resource_type(
                "default",
                ResourceType {
                    id: "device".into(),
                    title: "Device".into(),
                    group: String::new(),
                    description: String::new(),
                    fields: vec![FieldDef {
                        name: "serial".into(),
                        label: "Serial".into(),
                        field_type: FieldType::String,
                        enum_values: vec![],
                    }],
                    required_fields: vec![],
                    list_columns: vec![],
                    form_layout: vec![],
                    detail_sections: vec![],
                    references: vec![],
                    validation_rules: vec![ValidationRule {
                        rule_type: ValidationRuleType::Unique,
                        field: "serial".into(),
                        message: String::new(),
                        pattern: String::new(),
                        min: None,
                        max: None,
                        values: vec![],
                        target_type: String::new(),
                    }],
                },
            )
            .await
            .unwrap();

        let mut first_spec = serde_json::Map::new();
        first_spec.insert("serial".into(), json!("A"));
        store
            .upsert_resource("default", resource("device", "d1", "Device 1", first_spec))
            .await
            .unwrap();

        let mut second_spec = serde_json::Map::new();
        second_spec.insert("serial".into(), json!("B"));
        store
            .upsert_resource("default", resource("device", "d2", "Device 2", second_spec))
            .await
            .unwrap();

        let mut duplicate_spec = serde_json::Map::new();
        duplicate_spec.insert("serial".into(), json!("A"));
        let err = api
            .create_or_update_resource(
                "default",
                resource("device", "d2", "Device 2", duplicate_spec),
            )
            .await
            .expect_err("expected validation error");
        let api_err = err.downcast_ref::<ApiError>().expect("api error");
        match api_err {
            ApiError::Validation(msg) => {
                assert!(msg.contains("duplicate value for unique field"))
            }
            other => panic!("unexpected error: {other:?}"),
        }
    }

    #[tokio::test]
    async fn import_preview_supports_resource_only_import_with_existing_types() {
        let store = MemoryStore::default();
        let api = ApiService::new(store.clone());
        store
            .upsert_resource_type(
                "default",
                ResourceType {
                    id: "provider".into(),
                    title: "Provider".into(),
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

        let yaml = r#"
version: 1
resource_types: []
resources:
  - id: oci
    type: provider
    name: OCI
    spec:
      status: active
"#;
        let preview = api.import_catalog_preview("default", yaml).await.unwrap();
        assert!(preview.validation_errors.is_empty());
        assert_eq!(
            preview.resources_to_create,
            vec!["provider/oci".to_string()]
        );
    }

    #[tokio::test]
    async fn import_apply_supports_cross_type_references_in_same_import() {
        let store = MemoryStore::default();
        let api = ApiService::new(store.clone());

        let yaml = r#"
version: 1
resource_types:
  - id: site
    title: Site
    fields: []
    required_fields: []
    list_columns: []
    form_layout: []
    detail_sections: []
    references: []
    validation_rules: []
  - id: zone
    title: Zone
    fields: []
    required_fields: []
    list_columns: []
    form_layout: []
    detail_sections: []
    references: []
    validation_rules: []
  - id: network
    title: Network
    fields:
      - name: site
        label: Site
        type: reference
      - name: zone
        label: Zone
        type: reference
    required_fields: []
    list_columns: []
    form_layout: []
    detail_sections: []
    references:
      - field: site
        target_type: site
      - field: zone
        target_type: zone
    validation_rules: []
resources:
  - id: ONP-JP-KNG-01
    type: site
    name: Site 1
    spec: {}
  - id: client
    type: zone
    name: Client
    spec: {}
  - id: ONP-JP-KNG-01-CLIENT-V100
    type: network
    name: Net 1
    spec:
      site: ONP-JP-KNG-01
      zone: client
"#;

        let preview = api.import_catalog_preview("default", yaml).await.unwrap();
        assert!(preview.validation_errors.is_empty());
        api.import_catalog_yaml("default", yaml).await.unwrap();
        api.validate_catalog("default").await.unwrap();
    }
}
