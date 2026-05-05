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
    let type_map: HashMap<_, _> = types.iter().map(|t| (t.id.as_str(), t)).collect();
    let id_set: HashSet<_> = resources.iter().map(|r| r.metadata.id.as_str()).collect();
    let mut seen = HashSet::new();

    for r in resources {
        if r.metadata.id.is_empty()
            || r.metadata.resource_type.is_empty()
            || r.metadata.name.is_empty()
        {
            return Err(ValidationError::MissingMetadata);
        }
        if !seen.insert(r.metadata.id.clone()) {
            return Err(ValidationError::DuplicateResourceId(r.metadata.id.clone()));
        }

        let Some(rt) = type_map.get(r.metadata.resource_type.as_str()) else {
            return Err(ValidationError::UnknownResourceType(
                r.metadata.resource_type.clone(),
            ));
        };

        for required in &rt.required_fields {
            if !r.spec.contains_key(required) {
                return Err(ValidationError::MissingRequiredField(required.clone()));
            }
        }

        for f in &rt.fields {
            if let Some(v) = r.spec.get(&f.name) {
                validate_field_value(f, v)?;
            }
        }

        for rule in &rt.validation_rules {
            if let Some(v) = r.spec.get(&rule.field) {
                validate_rule_value(rule, v, &r.metadata.resource_type, &id_set)?;
            }
        }
    }

    Ok(())
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
    id_set: &HashSet<&str>,
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
            if !id_set.contains(reference_id) {
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
}
