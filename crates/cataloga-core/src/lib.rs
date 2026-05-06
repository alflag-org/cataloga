use std::collections::{HashMap, HashSet};

use ipnet::IpNet;
use regex::Regex;
use serde::{Deserialize, Serialize};
use serde_json::{Map, Value};
use thiserror::Error;

#[derive(Debug, Clone, Serialize, Deserialize, PartialEq)]
pub struct ResourceType {
    pub id: String,
    pub title: String,
    #[serde(default)]
    pub group: String,
    #[serde(default)]
    pub description: String,
    #[serde(default)]
    pub fields: Vec<FieldDef>,
    #[serde(default)]
    pub required_fields: Vec<String>,
    #[serde(default)]
    pub list_columns: Vec<String>,
    #[serde(default)]
    pub form_layout: Vec<FormSection>,
    #[serde(default)]
    pub detail_sections: Vec<DetailSection>,
    #[serde(default)]
    pub references: Vec<ReferenceDef>,
    #[serde(default)]
    pub validation_rules: Vec<ValidationRule>,
}

#[derive(Debug, Clone, Serialize, Deserialize, PartialEq)]
pub struct FieldDef {
    pub name: String,
    pub label: String,
    #[serde(rename = "type")]
    pub field_type: FieldType,
    #[serde(default)]
    pub enum_values: Vec<String>,
}

#[derive(Debug, Clone, Serialize, Deserialize, PartialEq)]
#[serde(rename_all = "snake_case")]
pub enum FieldType {
    String,
    Text,
    Integer,
    Number,
    Boolean,
    Enum,
    Array,
    Json,
    Reference,
    ReferenceArray,
    Ip,
    Cidr,
    Url,
}

#[derive(Debug, Clone, Serialize, Deserialize, PartialEq)]
pub struct FormSection {
    pub title: String,
    pub fields: Vec<String>,
}

#[derive(Debug, Clone, Serialize, Deserialize, PartialEq)]
pub struct DetailSection {
    pub title: String,
    pub fields: Vec<String>,
}

#[derive(Debug, Clone, Serialize, Deserialize, PartialEq)]
pub struct ReferenceDef {
    pub field: String,
    pub target_type: String,
    #[serde(default)]
    pub multiple: bool,
}

#[derive(Debug, Clone, Serialize, Deserialize, PartialEq)]
pub struct ValidationRule {
    #[serde(rename = "type")]
    pub rule_type: ValidationRuleType,
    pub field: String,
    #[serde(default)]
    pub message: String,
    #[serde(default)]
    pub pattern: String,
    pub min: Option<f64>,
    pub max: Option<f64>,
    #[serde(default)]
    pub values: Vec<String>,
    #[serde(default)]
    pub target_type: String,
}

#[derive(Debug, Clone, Serialize, Deserialize, PartialEq)]
#[serde(rename_all = "snake_case")]
pub enum ValidationRuleType {
    Required,
    Enum,
    Regex,
    NumberRange,
    ReferenceExists,
    ReferenceType,
    Unique,
    Ip,
    Cidr,
    Url,
}

#[derive(Debug, Clone, Serialize, Deserialize, PartialEq)]
pub struct Resource {
    pub api_version: String,
    pub kind: String,
    pub metadata: Metadata,
    #[serde(default)]
    pub spec: Map<String, Value>,
    #[serde(default)]
    pub custom_fields: Map<String, Value>,
    #[serde(default)]
    pub dependencies: Map<String, Value>,
}

#[derive(Debug, Clone, Serialize, Deserialize, PartialEq)]
pub struct Metadata {
    pub id: String,
    #[serde(rename = "type")]
    pub resource_type: String,
    pub name: String,
    #[serde(default)]
    pub tags: HashMap<String, String>,
}

#[derive(Debug, Error)]
pub enum ValidationError {
    #[error("missing required metadata")]
    MissingMetadata,
    #[error("unknown resource type: {0}")]
    UnknownResourceType(String),
    #[error("duplicate resource id: {0}")]
    DuplicateResourceId(String),
    #[error("missing required field: {0}")]
    MissingRequiredField(String),
    #[error("invalid field type: {0}")]
    InvalidFieldType(String),
    #[error("invalid enum value: {0}")]
    InvalidEnumValue(String),
    #[error("invalid reference target: {0}")]
    InvalidReferenceTarget(String),
    #[error("invalid reference type: {0}")]
    InvalidReferenceType(String),
    #[error("invalid list column path: {0}")]
    InvalidListColumnPath(String),
    #[error("invalid view definition: {0}")]
    InvalidViewDefinition(String),
    #[error("invalid regex for field: {0}")]
    InvalidRegex(String),
    #[error("invalid number range for field: {0}")]
    InvalidNumberRange(String),
}

#[derive(Debug, Clone, Serialize, Deserialize, PartialEq)]
pub struct ResourceValidationIssue {
    pub resource_type: String,
    pub resource_id: String,
    pub field: String,
    pub message: String,
}

pub fn validate_resource_type(rt: &ResourceType) -> Result<(), ValidationError> {
    let field_names: HashSet<&str> = rt.fields.iter().map(|f| f.name.as_str()).collect();

    for col in &rt.list_columns {
        if !col.starts_with("metadata.") && !col.starts_with("spec.") {
            return Err(ValidationError::InvalidListColumnPath(col.clone()));
        }
    }

    for req in &rt.required_fields {
        if !field_names.contains(req.as_str()) {
            return Err(ValidationError::MissingRequiredField(req.clone()));
        }
    }

    for section in &rt.form_layout {
        for field in &section.fields {
            if !field_names.contains(field.as_str()) {
                return Err(ValidationError::InvalidViewDefinition(field.clone()));
            }
        }
    }

    for section in &rt.detail_sections {
        for field in &section.fields {
            if !field_names.contains(field.as_str()) {
                return Err(ValidationError::InvalidViewDefinition(field.clone()));
            }
        }
    }

    for reference in &rt.references {
        if !field_names.contains(reference.field.as_str()) {
            return Err(ValidationError::InvalidReferenceTarget(
                reference.field.clone(),
            ));
        }
    }

    for rule in &rt.validation_rules {
        if !field_names.contains(rule.field.as_str()) {
            return Err(ValidationError::InvalidViewDefinition(rule.field.clone()));
        }
        if matches!(rule.rule_type, ValidationRuleType::Regex)
            && !rule.pattern.is_empty()
            && Regex::new(&rule.pattern).is_err()
        {
            return Err(ValidationError::InvalidRegex(rule.field.clone()));
        }
        if matches!(rule.rule_type, ValidationRuleType::NumberRange)
            && matches!((rule.min, rule.max), (Some(min), Some(max)) if min > max)
        {
            return Err(ValidationError::InvalidNumberRange(rule.field.clone()));
        }
    }

    Ok(())
}

pub fn validate_resources(
    types: &[ResourceType],
    resources: &[Resource],
) -> Result<(), ValidationError> {
    let issues = validate_resources_detailed(types, resources);
    if let Some(issue) = issues.first() {
        if issue.message.contains("missing required metadata") {
            return Err(ValidationError::MissingMetadata);
        }
        if issue.message.contains("duplicate resource id:") {
            return Err(ValidationError::DuplicateResourceId(
                issue.resource_id.clone(),
            ));
        }
        if issue.message.contains("unknown resource type:") {
            return Err(ValidationError::UnknownResourceType(
                issue.resource_type.clone(),
            ));
        }
        if issue.message.contains("missing required field:") {
            return Err(ValidationError::MissingRequiredField(issue.field.clone()));
        }
        if issue.message.contains("invalid field type:") {
            return Err(ValidationError::InvalidFieldType(issue.field.clone()));
        }
        if issue.message.contains("invalid enum value:") {
            return Err(ValidationError::InvalidEnumValue(issue.field.clone()));
        }
        if issue.message.contains("duplicate value for unique field:") {
            return Err(ValidationError::InvalidFieldType(issue.field.clone()));
        }
        if issue.message.contains("references missing") {
            return Err(ValidationError::InvalidReferenceTarget(issue.field.clone()));
        }
        if issue.message.contains("must reference") {
            return Err(ValidationError::InvalidReferenceType(issue.field.clone()));
        }
        return Err(ValidationError::InvalidFieldType(issue.field.clone()));
    }

    Ok(())
}

pub fn validate_resources_detailed(
    types: &[ResourceType],
    resources: &[Resource],
) -> Vec<ResourceValidationIssue> {
    let type_map: HashMap<_, _> = types.iter().map(|t| (t.id.as_str(), t)).collect();
    let mut issues = Vec::new();
    let mut seen = HashSet::new();
    let mut resource_index: HashMap<(String, String), &Resource> = HashMap::new();
    let mut id_to_types: HashMap<String, HashSet<String>> = HashMap::new();

    for r in resources {
        let key = (r.metadata.resource_type.clone(), r.metadata.id.clone());
        if !seen.insert(key.clone()) {
            issues.push(ResourceValidationIssue {
                resource_type: r.metadata.resource_type.clone(),
                resource_id: r.metadata.id.clone(),
                field: "metadata.id".to_string(),
                message: format!(
                    "duplicate resource id: {}/{}",
                    r.metadata.resource_type, r.metadata.id
                ),
            });
            continue;
        }
        id_to_types
            .entry(r.metadata.id.clone())
            .or_default()
            .insert(r.metadata.resource_type.clone());
        resource_index.insert(key, r);
    }

    for r in resources {
        let context = format!("{}/{}", r.metadata.resource_type, r.metadata.id);
        if r.metadata.id.is_empty()
            || r.metadata.resource_type.is_empty()
            || r.metadata.name.is_empty()
        {
            issues.push(ResourceValidationIssue {
                resource_type: r.metadata.resource_type.clone(),
                resource_id: r.metadata.id.clone(),
                field: "metadata".to_string(),
                message: format!("{context} missing required metadata"),
            });
            continue;
        }
        let Some(rt) = type_map.get(r.metadata.resource_type.as_str()) else {
            issues.push(ResourceValidationIssue {
                resource_type: r.metadata.resource_type.clone(),
                resource_id: r.metadata.id.clone(),
                field: "metadata.type".to_string(),
                message: format!(
                    "{context} unknown resource type: {}",
                    r.metadata.resource_type
                ),
            });
            continue;
        };

        for required in &rt.required_fields {
            if !r.spec.contains_key(required) {
                issues.push(ResourceValidationIssue {
                    resource_type: r.metadata.resource_type.clone(),
                    resource_id: r.metadata.id.clone(),
                    field: required.clone(),
                    message: format!("{context} missing required field: {required}"),
                });
            }
        }

        for f in &rt.fields {
            if let Some(v) = r.spec.get(&f.name)
                && let Err(err) = validate_field_value(f, v)
            {
                let message = match err {
                    ValidationError::InvalidEnumValue(_) => {
                        format!("{context} invalid enum value: {}", f.name)
                    }
                    _ => format!("{context} invalid field type: {}", f.name),
                };
                issues.push(ResourceValidationIssue {
                    resource_type: r.metadata.resource_type.clone(),
                    resource_id: r.metadata.id.clone(),
                    field: f.name.clone(),
                    message,
                });
            }
        }

        for rule in &rt.validation_rules {
            if let Some(v) = r.spec.get(&rule.field)
                && validate_rule_value(rule, v, &r.metadata.resource_type, &id_to_types).is_err()
            {
                issues.push(ResourceValidationIssue {
                    resource_type: r.metadata.resource_type.clone(),
                    resource_id: r.metadata.id.clone(),
                    field: rule.field.clone(),
                    message: format!("{context} invalid value for field: {}", rule.field),
                });
            }
        }

        for reference in &rt.references {
            let Some(value) = r.spec.get(&reference.field) else {
                continue;
            };
            if reference.multiple {
                let Some(items) = value.as_array() else {
                    issues.push(ResourceValidationIssue {
                        resource_type: r.metadata.resource_type.clone(),
                        resource_id: r.metadata.id.clone(),
                        field: reference.field.clone(),
                        message: format!("{context} {} must be an array", reference.field),
                    });
                    continue;
                };
                for item in items {
                    let Some(target_id) = item.as_str() else {
                        issues.push(ResourceValidationIssue {
                            resource_type: r.metadata.resource_type.clone(),
                            resource_id: r.metadata.id.clone(),
                            field: reference.field.clone(),
                            message: format!(
                                "{context} {} contains non-string value",
                                reference.field
                            ),
                        });
                        continue;
                    };
                    validate_reference_target(
                        &mut issues,
                        &resource_index,
                        &id_to_types,
                        r,
                        &reference.field,
                        &reference.target_type,
                        target_id,
                    );
                }
            } else {
                let Some(target_id) = value.as_str() else {
                    issues.push(ResourceValidationIssue {
                        resource_type: r.metadata.resource_type.clone(),
                        resource_id: r.metadata.id.clone(),
                        field: reference.field.clone(),
                        message: format!("{context} {} must be a string", reference.field),
                    });
                    continue;
                };
                validate_reference_target(
                    &mut issues,
                    &resource_index,
                    &id_to_types,
                    r,
                    &reference.field,
                    &reference.target_type,
                    target_id,
                );
            }
        }
    }

    for rt in types {
        for rule in rt
            .validation_rules
            .iter()
            .filter(|rule| matches!(rule.rule_type, ValidationRuleType::Unique))
        {
            let mut seen_values: HashMap<String, String> = HashMap::new();
            for r in resources
                .iter()
                .filter(|r| r.metadata.resource_type == rt.id)
            {
                let Some(value) = r.spec.get(&rule.field) else {
                    continue;
                };
                let stable = serde_json::to_string(value).unwrap_or_default();
                if let Some(first_id) = seen_values.get(&stable) {
                    issues.push(ResourceValidationIssue {
                        resource_type: rt.id.clone(),
                        resource_id: r.metadata.id.clone(),
                        field: rule.field.clone(),
                        message: format!(
                            "{}/{} duplicate value for unique field: {}.{} (already used by {})",
                            rt.id, r.metadata.id, rt.id, rule.field, first_id
                        ),
                    });
                } else {
                    seen_values.insert(stable, r.metadata.id.clone());
                }
            }
        }
    }

    issues
}

fn validate_reference_target(
    issues: &mut Vec<ResourceValidationIssue>,
    resource_index: &HashMap<(String, String), &Resource>,
    id_to_types: &HashMap<String, HashSet<String>>,
    resource: &Resource,
    field: &str,
    target_type: &str,
    target_id: &str,
) {
    let context = format!(
        "{}/{}",
        resource.metadata.resource_type, resource.metadata.id
    );
    let target_key = (target_type.to_string(), target_id.to_string());
    if resource_index.contains_key(&target_key) {
        return;
    }
    if let Some(types) = id_to_types.get(target_id)
        && let Some(actual_type) = types.iter().find(|t| t.as_str() != target_type)
    {
        issues.push(ResourceValidationIssue {
            resource_type: resource.metadata.resource_type.clone(),
            resource_id: resource.metadata.id.clone(),
            field: field.to_string(),
            message: format!(
                "{context} {field} must reference {target_type} but references {actual_type}"
            ),
        });
        return;
    }
    issues.push(ResourceValidationIssue {
        resource_type: resource.metadata.resource_type.clone(),
        resource_id: resource.metadata.id.clone(),
        field: field.to_string(),
        message: format!("{context} {field} references missing {target_type}: {target_id}"),
    });
}

fn validate_field_value(f: &FieldDef, v: &Value) -> Result<(), ValidationError> {
    match f.field_type {
        FieldType::String | FieldType::Text | FieldType::Reference => v
            .as_str()
            .map(|_| ())
            .ok_or_else(|| ValidationError::InvalidFieldType(f.name.clone())),
        FieldType::Integer => v
            .as_i64()
            .map(|_| ())
            .ok_or_else(|| ValidationError::InvalidFieldType(f.name.clone())),
        FieldType::Number => v
            .as_f64()
            .map(|_| ())
            .ok_or_else(|| ValidationError::InvalidFieldType(f.name.clone())),
        FieldType::Boolean => v
            .as_bool()
            .map(|_| ())
            .ok_or_else(|| ValidationError::InvalidFieldType(f.name.clone())),
        FieldType::Enum => {
            let Some(s) = v.as_str() else {
                return Err(ValidationError::InvalidFieldType(f.name.clone()));
            };
            if f.enum_values.iter().any(|e| e == s) {
                Ok(())
            } else {
                Err(ValidationError::InvalidEnumValue(f.name.clone()))
            }
        }
        FieldType::Array => v
            .as_array()
            .map(|_| ())
            .ok_or_else(|| ValidationError::InvalidFieldType(f.name.clone())),
        FieldType::Json => v
            .as_object()
            .map(|_| ())
            .ok_or_else(|| ValidationError::InvalidFieldType(f.name.clone())),
        FieldType::ReferenceArray => v
            .as_array()
            .filter(|items| items.iter().all(Value::is_string))
            .map(|_| ())
            .ok_or_else(|| ValidationError::InvalidFieldType(f.name.clone())),
        FieldType::Ip => {
            let Some(s) = v.as_str() else {
                return Err(ValidationError::InvalidFieldType(f.name.clone()));
            };
            s.parse::<std::net::IpAddr>()
                .map(|_| ())
                .map_err(|_| ValidationError::InvalidFieldType(f.name.clone()))
        }
        FieldType::Cidr => {
            let Some(s) = v.as_str() else {
                return Err(ValidationError::InvalidFieldType(f.name.clone()));
            };
            s.parse::<IpNet>()
                .map(|_| ())
                .map_err(|_| ValidationError::InvalidFieldType(f.name.clone()))
        }
        FieldType::Url => {
            let Some(s) = v.as_str() else {
                return Err(ValidationError::InvalidFieldType(f.name.clone()));
            };
            url::Url::parse(s)
                .map(|_| ())
                .map_err(|_| ValidationError::InvalidFieldType(f.name.clone()))
        }
    }
}

fn validate_rule_value(
    rule: &ValidationRule,
    v: &Value,
    current_type: &str,
    id_to_types: &HashMap<String, HashSet<String>>,
) -> Result<(), ValidationError> {
    match rule.rule_type {
        ValidationRuleType::Required => {
            if v.is_null() {
                return Err(ValidationError::MissingRequiredField(rule.field.clone()));
            }
        }
        ValidationRuleType::Enum => {
            let Some(s) = v.as_str() else {
                return Err(ValidationError::InvalidFieldType(rule.field.clone()));
            };
            if !rule.values.iter().any(|e| e == s) {
                return Err(ValidationError::InvalidEnumValue(rule.field.clone()));
            }
        }
        ValidationRuleType::Regex => {
            let Some(s) = v.as_str() else {
                return Err(ValidationError::InvalidFieldType(rule.field.clone()));
            };
            if !rule.pattern.is_empty() {
                let re = Regex::new(&rule.pattern)
                    .map_err(|_| ValidationError::InvalidRegex(rule.field.clone()))?;
                if !re.is_match(s) {
                    return Err(ValidationError::InvalidFieldType(rule.field.clone()));
                }
            }
        }
        ValidationRuleType::NumberRange => {
            let Some(n) = v.as_f64() else {
                return Err(ValidationError::InvalidFieldType(rule.field.clone()));
            };
            if let Some(min) = rule.min
                && n < min
            {
                return Err(ValidationError::InvalidNumberRange(rule.field.clone()));
            }
            if let Some(max) = rule.max
                && n > max
            {
                return Err(ValidationError::InvalidNumberRange(rule.field.clone()));
            }
        }
        ValidationRuleType::ReferenceExists => {
            let Some(reference_id) = v.as_str() else {
                return Err(ValidationError::InvalidReferenceTarget(rule.field.clone()));
            };
            if !id_to_types.contains_key(reference_id) {
                return Err(ValidationError::InvalidReferenceTarget(rule.field.clone()));
            }
        }
        ValidationRuleType::ReferenceType => {
            if rule.target_type.is_empty() || current_type.is_empty() {
                return Err(ValidationError::InvalidReferenceType(rule.field.clone()));
            }
        }
        ValidationRuleType::Unique => {}
        ValidationRuleType::Ip => {
            let Some(ip) = v.as_str() else {
                return Err(ValidationError::InvalidFieldType(rule.field.clone()));
            };
            ip.parse::<std::net::IpAddr>()
                .map_err(|_| ValidationError::InvalidFieldType(rule.field.clone()))?;
        }
        ValidationRuleType::Cidr => {
            let Some(cidr) = v.as_str() else {
                return Err(ValidationError::InvalidFieldType(rule.field.clone()));
            };
            cidr.parse::<IpNet>()
                .map_err(|_| ValidationError::InvalidFieldType(rule.field.clone()))?;
        }
        ValidationRuleType::Url => {
            let Some(url_value) = v.as_str() else {
                return Err(ValidationError::InvalidFieldType(rule.field.clone()));
            };
            url::Url::parse(url_value)
                .map_err(|_| ValidationError::InvalidFieldType(rule.field.clone()))?;
        }
    }
    Ok(())
}

pub fn export_yaml(types: &[ResourceType], resources: &[Resource]) -> anyhow::Result<String> {
    #[derive(Serialize)]
    struct Registry<'a> {
        resource_types: &'a [ResourceType],
        resources: &'a [Resource],
    }

    Ok(serde_yaml::to_string(&Registry {
        resource_types: types,
        resources,
    })?)
}

pub fn import_yaml(input: &str) -> anyhow::Result<(Vec<ResourceType>, Vec<Resource>)> {
    #[derive(Deserialize)]
    struct Registry {
        resource_types: Vec<ResourceType>,
        resources: Vec<Resource>,
    }

    let parsed: Registry = serde_yaml::from_str(input)?;
    Ok((parsed.resource_types, parsed.resources))
}

#[cfg(test)]
mod tests {
    use super::*;
    use serde_json::json;

    fn sample_type() -> ResourceType {
        ResourceType {
            id: "device".into(),
            title: "Device".into(),
            group: "compute".into(),
            description: "Device type".into(),
            fields: vec![
                FieldDef {
                    name: "role".into(),
                    label: "Role".into(),
                    field_type: FieldType::Enum,
                    enum_values: vec!["router".into(), "switch".into()],
                },
                FieldDef {
                    name: "mgmt_ip".into(),
                    label: "Management IP".into(),
                    field_type: FieldType::Ip,
                    enum_values: vec![],
                },
            ],
            required_fields: vec!["role".into()],
            list_columns: vec!["metadata.name".into(), "spec.role".into()],
            form_layout: vec![FormSection {
                title: "General".into(),
                fields: vec!["role".into(), "mgmt_ip".into()],
            }],
            detail_sections: vec![DetailSection {
                title: "Details".into(),
                fields: vec!["role".into(), "mgmt_ip".into()],
            }],
            references: vec![],
            validation_rules: vec![ValidationRule {
                rule_type: ValidationRuleType::Required,
                field: "role".into(),
                message: String::new(),
                pattern: String::new(),
                min: None,
                max: None,
                values: vec![],
                target_type: String::new(),
            }],
        }
    }

    fn sample_resource() -> Resource {
        let mut spec = Map::new();
        spec.insert("role".into(), json!("router"));
        spec.insert("mgmt_ip".into(), json!("10.0.0.1"));

        Resource {
            api_version: "cataloga.io/v1".into(),
            kind: "Resource".into(),
            metadata: Metadata {
                id: "r1".into(),
                resource_type: "device".into(),
                name: "edge01".into(),
                tags: HashMap::new(),
            },
            spec,
            custom_fields: Map::new(),
            dependencies: Map::new(),
        }
    }

    #[test]
    fn validate_ok() {
        let rt = sample_type();
        let r = sample_resource();
        assert!(validate_resource_type(&rt).is_ok());
        assert!(validate_resources(&[rt], &[r]).is_ok());
    }

    #[test]
    fn enum_validation_fails() {
        let rt = sample_type();
        let mut r = sample_resource();
        r.spec.insert("role".into(), json!("invalid"));
        assert!(matches!(
            validate_resources(&[rt], &[r]),
            Err(ValidationError::InvalidEnumValue(_))
        ));
    }

    #[test]
    fn round_trip_yaml() {
        let rt = sample_type();
        let r = sample_resource();
        let out = export_yaml(std::slice::from_ref(&rt), std::slice::from_ref(&r)).unwrap();
        let (types, resources) = import_yaml(&out).unwrap();
        assert_eq!(types[0], rt);
        assert_eq!(resources[0], r);
    }

    #[test]
    fn reference_validation_passes_when_target_exists() {
        let ip_type = ResourceType {
            id: "ip_address".into(),
            title: "IP".into(),
            group: String::new(),
            description: String::new(),
            fields: vec![FieldDef {
                name: "address".into(),
                label: "address".into(),
                field_type: FieldType::String,
                enum_values: vec![],
            }],
            required_fields: vec![],
            list_columns: vec![],
            form_layout: vec![],
            detail_sections: vec![],
            references: vec![],
            validation_rules: vec![],
        };
        let vm_type = ResourceType {
            id: "vm".into(),
            title: "VM".into(),
            group: String::new(),
            description: String::new(),
            fields: vec![FieldDef {
                name: "primary_ip".into(),
                label: "primary_ip".into(),
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
        };
        let mut ip = sample_resource();
        ip.metadata.resource_type = "ip_address".into();
        ip.metadata.id = "10.0.0.1".into();
        let mut vm = sample_resource();
        vm.metadata.resource_type = "vm".into();
        vm.spec = Map::new();
        vm.spec.insert("primary_ip".into(), json!("10.0.0.1"));
        let issues = validate_resources_detailed(&[ip_type, vm_type], &[ip, vm]);
        assert!(issues.is_empty());
    }

    #[test]
    fn reference_validation_fails_when_target_missing() {
        let vm_type = ResourceType {
            id: "vm".into(),
            title: "VM".into(),
            group: String::new(),
            description: String::new(),
            fields: vec![FieldDef {
                name: "primary_ip".into(),
                label: "primary_ip".into(),
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
        };
        let mut vm = sample_resource();
        vm.metadata.resource_type = "vm".into();
        vm.spec = Map::new();
        vm.spec.insert("primary_ip".into(), json!("10.0.0.999"));
        let issues = validate_resources_detailed(&[vm_type], &[vm]);
        assert!(
            issues
                .iter()
                .any(|i| i.message.contains("references missing ip_address"))
        );
    }

    #[test]
    fn reference_validation_fails_when_target_type_wrong() {
        let device_type = ResourceType {
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
        };
        let vm_type = ResourceType {
            id: "vm".into(),
            title: "VM".into(),
            group: String::new(),
            description: String::new(),
            fields: vec![FieldDef {
                name: "host".into(),
                label: "host".into(),
                field_type: FieldType::Reference,
                enum_values: vec![],
            }],
            required_fields: vec![],
            list_columns: vec![],
            form_layout: vec![],
            detail_sections: vec![],
            references: vec![ReferenceDef {
                field: "host".into(),
                target_type: "ip_address".into(),
                multiple: false,
            }],
            validation_rules: vec![],
        };
        let mut target = sample_resource();
        target.metadata.resource_type = "device".into();
        target.metadata.id = "node-1".into();
        let mut vm = sample_resource();
        vm.metadata.resource_type = "vm".into();
        vm.spec = Map::new();
        vm.spec.insert("host".into(), json!("node-1"));
        let issues = validate_resources_detailed(&[device_type, vm_type], &[target, vm]);
        assert!(issues.iter().any(|i| {
            i.message
                .contains("must reference ip_address but references device")
        }));
    }

    #[test]
    fn reference_array_validation_passes_with_existing_targets() {
        let service_type = ResourceType {
            id: "service".into(),
            title: "Service".into(),
            group: String::new(),
            description: String::new(),
            fields: vec![FieldDef {
                name: "depends_on".into(),
                label: "depends_on".into(),
                field_type: FieldType::ReferenceArray,
                enum_values: vec![],
            }],
            required_fields: vec![],
            list_columns: vec![],
            form_layout: vec![],
            detail_sections: vec![],
            references: vec![ReferenceDef {
                field: "depends_on".into(),
                target_type: "service".into(),
                multiple: true,
            }],
            validation_rules: vec![],
        };
        let mut s1 = sample_resource();
        s1.metadata.resource_type = "service".into();
        s1.metadata.id = "a".into();
        s1.spec = Map::new();
        let mut s2 = sample_resource();
        s2.metadata.resource_type = "service".into();
        s2.metadata.id = "b".into();
        s2.spec = Map::new();
        s2.spec.insert("depends_on".into(), json!(["a"]));
        let issues = validate_resources_detailed(&[service_type], &[s1, s2]);
        assert!(issues.is_empty());
    }

    #[test]
    fn reference_array_validation_fails_with_missing_target() {
        let service_type = ResourceType {
            id: "service".into(),
            title: "Service".into(),
            group: String::new(),
            description: String::new(),
            fields: vec![FieldDef {
                name: "depends_on".into(),
                label: "depends_on".into(),
                field_type: FieldType::ReferenceArray,
                enum_values: vec![],
            }],
            required_fields: vec![],
            list_columns: vec![],
            form_layout: vec![],
            detail_sections: vec![],
            references: vec![ReferenceDef {
                field: "depends_on".into(),
                target_type: "service".into(),
                multiple: true,
            }],
            validation_rules: vec![],
        };
        let mut s = sample_resource();
        s.metadata.resource_type = "service".into();
        s.metadata.id = "b".into();
        s.spec = Map::new();
        s.spec.insert("depends_on".into(), json!(["a"]));
        let issues = validate_resources_detailed(&[service_type], &[s]);
        assert!(
            issues
                .iter()
                .any(|i| i.message.contains("references missing service: a"))
        );
    }

    #[test]
    fn unique_validation_passes_with_distinct_values() {
        let mut rt = sample_type();
        rt.id = "ip_address".into();
        rt.required_fields = vec![];
        rt.fields = vec![FieldDef {
            name: "address".into(),
            label: "address".into(),
            field_type: FieldType::String,
            enum_values: vec![],
        }];
        rt.validation_rules = vec![ValidationRule {
            rule_type: ValidationRuleType::Unique,
            field: "address".into(),
            message: String::new(),
            pattern: String::new(),
            min: None,
            max: None,
            values: vec![],
            target_type: String::new(),
        }];
        let mut a = sample_resource();
        a.metadata.resource_type = "ip_address".into();
        a.metadata.id = "ip1".into();
        a.spec = Map::new();
        a.spec.insert("address".into(), json!("10.0.0.1"));
        let mut b = sample_resource();
        b.metadata.resource_type = "ip_address".into();
        b.metadata.id = "ip2".into();
        b.spec = Map::new();
        b.spec.insert("address".into(), json!("10.0.0.2"));
        assert!(validate_resources_detailed(&[rt], &[a, b]).is_empty());
    }

    #[test]
    fn unique_validation_fails_with_duplicate_values() {
        let mut rt = sample_type();
        rt.id = "ip_address".into();
        rt.required_fields = vec![];
        rt.fields = vec![FieldDef {
            name: "address".into(),
            label: "address".into(),
            field_type: FieldType::String,
            enum_values: vec![],
        }];
        rt.validation_rules = vec![ValidationRule {
            rule_type: ValidationRuleType::Unique,
            field: "address".into(),
            message: String::new(),
            pattern: String::new(),
            min: None,
            max: None,
            values: vec![],
            target_type: String::new(),
        }];
        let mut a = sample_resource();
        a.metadata.resource_type = "ip_address".into();
        a.metadata.id = "ip1".into();
        a.spec = Map::new();
        a.spec.insert("address".into(), json!("10.0.0.1"));
        let mut b = sample_resource();
        b.metadata.resource_type = "ip_address".into();
        b.metadata.id = "ip2".into();
        b.spec = Map::new();
        b.spec.insert("address".into(), json!("10.0.0.1"));
        let issues = validate_resources_detailed(&[rt], &[a, b]);
        assert!(
            issues
                .iter()
                .any(|i| i.message.contains("duplicate value for unique field"))
        );
    }

    #[test]
    fn unique_validation_ignores_missing_optional_values() {
        let mut rt = sample_type();
        rt.id = "ip_address".into();
        rt.required_fields = vec![];
        rt.fields = vec![FieldDef {
            name: "address".into(),
            label: "address".into(),
            field_type: FieldType::String,
            enum_values: vec![],
        }];
        rt.validation_rules = vec![ValidationRule {
            rule_type: ValidationRuleType::Unique,
            field: "address".into(),
            message: String::new(),
            pattern: String::new(),
            min: None,
            max: None,
            values: vec![],
            target_type: String::new(),
        }];
        let mut a = sample_resource();
        a.metadata.resource_type = "ip_address".into();
        a.metadata.id = "ip1".into();
        a.spec = Map::new();
        let mut b = sample_resource();
        b.metadata.resource_type = "ip_address".into();
        b.metadata.id = "ip2".into();
        b.spec = Map::new();
        assert!(validate_resources_detailed(&[rt], &[a, b]).is_empty());
    }
}
