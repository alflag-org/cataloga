use cataloga_api::{ApiError as ServiceError, ApiService};
#[cfg(test)]
use cataloga_api::{ApiMethod, CANONICAL_API_ROUTES};
use cataloga_core::{Resource, ResourceType};
use cataloga_store_d1::D1Store;
use serde::Deserialize;
use serde_json::{Value, json};
use worker::*;

const CATALOG_ID: &str = "default";

#[derive(Deserialize)]
struct ImportRequest {
    yaml: String,
}

#[derive(Clone, Copy)]
enum ErrorKind {
    BadRequest,
    NotFound,
    Validation,
    Conflict,
    Internal,
}

impl ErrorKind {
    fn as_str(self) -> &'static str {
        match self {
            Self::BadRequest => "bad_request",
            Self::NotFound => "not_found",
            Self::Validation => "validation",
            Self::Conflict => "conflict",
            Self::Internal => "internal",
        }
    }

    fn status(self) -> u16 {
        match self {
            Self::BadRequest => 400,
            Self::NotFound => 404,
            Self::Validation => 422,
            Self::Conflict => 409,
            Self::Internal => 500,
        }
    }
}

struct ApiError {
    kind: ErrorKind,
    message: String,
    log_message: String,
}

impl ApiError {
    fn bad_request(message: impl Into<String>) -> Self {
        let m = message.into();
        Self {
            kind: ErrorKind::BadRequest,
            message: m.clone(),
            log_message: m,
        }
    }

    fn not_found(message: impl Into<String>) -> Self {
        let m = message.into();
        Self {
            kind: ErrorKind::NotFound,
            message: m.clone(),
            log_message: m,
        }
    }

    fn internal(message: impl Into<String>) -> Self {
        Self {
            kind: ErrorKind::Internal,
            message: "internal server error".to_string(),
            log_message: message.into(),
        }
    }

    fn from_service_error(err: anyhow::Error) -> Self {
        if let Some(service_err) = err.downcast_ref::<ServiceError>() {
            return match service_err {
                ServiceError::Validation(msg) => Self {
                    kind: ErrorKind::Validation,
                    message: msg.clone(),
                    log_message: msg.clone(),
                },
                ServiceError::Conflict(msg) => Self {
                    kind: ErrorKind::Conflict,
                    message: msg.clone(),
                    log_message: msg.clone(),
                },
                ServiceError::NotFound(msg) => Self::not_found(msg.clone()),
                ServiceError::BadRequest(msg) => Self::bad_request(msg.clone()),
                ServiceError::Internal(msg) => Self::internal(msg.clone()),
            };
        }

        let msg = err.to_string();
        let lower = msg.to_ascii_lowercase();
        if lower.contains("validation") {
            Self {
                kind: ErrorKind::Validation,
                message: msg.clone(),
                log_message: msg,
            }
        } else if lower.contains("conflict") {
            Self {
                kind: ErrorKind::Conflict,
                message: msg.clone(),
                log_message: msg,
            }
        } else if lower.contains("not found") {
            Self::not_found(msg)
        } else {
            Self::internal(msg)
        }
    }
}

#[derive(Default)]
struct RouteContext {
    route: &'static str,
    target_type: Option<String>,
    target_id: Option<String>,
}

struct HandledResponse {
    response: Response,
    route: RouteContext,
}

fn json_response<T: serde::Serialize>(value: &T) -> std::result::Result<Response, ApiError> {
    Response::from_json(value).map_err(|e| ApiError::internal(e.to_string()))
}

fn empty_response() -> std::result::Result<Response, ApiError> {
    Response::empty().map_err(|e| ApiError::internal(e.to_string()))
}

fn text_response(body: String) -> std::result::Result<Response, ApiError> {
    Response::ok(body).map_err(|e| ApiError::internal(e.to_string()))
}

fn log_json(value: Value) {
    console_log!("{}", value.to_string());
}

fn maybe_non_empty(value: Option<String>) -> Option<String> {
    value.and_then(|v| if v.is_empty() { None } else { Some(v) })
}

fn error_response(err: &ApiError) -> std::result::Result<Response, ApiError> {
    let response = Response::from_json(&json!({
        "error": {
            "kind": err.kind.as_str(),
            "message": err.message,
        }
    }))
    .map_err(|e| ApiError::internal(e.to_string()))?;
    Ok(response.with_status(err.kind.status()))
}

struct RequestLog<'a> {
    event: &'static str,
    method: &'a Method,
    path: &'a str,
    route: &'a str,
    status: u16,
    duration_ms: u64,
    cf_ray: Option<&'a str>,
    target_type: Option<&'a str>,
    target_id: Option<&'a str>,
    error_kind: Option<&'a str>,
    error_message: Option<&'a str>,
}

fn emit_request_log(log: RequestLog<'_>) {
    let mut obj = serde_json::Map::new();
    obj.insert("event".into(), json!(log.event));
    obj.insert("method".into(), json!(log.method.to_string()));
    obj.insert("path".into(), json!(log.path));
    obj.insert("route".into(), json!(log.route));
    obj.insert("status".into(), json!(log.status));
    obj.insert("duration_ms".into(), json!(log.duration_ms));
    obj.insert("catalog_id".into(), json!(CATALOG_ID));
    if let Some(v) = log.cf_ray {
        obj.insert("cf_ray".into(), json!(v));
    }
    if let Some(v) = log.target_type {
        obj.insert("target_type".into(), json!(v));
    }
    if let Some(v) = log.target_id {
        obj.insert("target_id".into(), json!(v));
    }
    if let Some(v) = log.error_kind {
        obj.insert("error_kind".into(), json!(v));
    }
    if let Some(v) = log.error_message {
        obj.insert("error_message".into(), json!(v));
    }
    log_json(Value::Object(obj));
}

fn emit_operation_log(event: &'static str, target_type: Option<&str>, target_id: Option<&str>) {
    let mut obj = serde_json::Map::new();
    obj.insert("event".into(), json!(event));
    obj.insert("catalog_id".into(), json!(CATALOG_ID));
    if let Some(v) = target_type {
        obj.insert("target_type".into(), json!(v));
    }
    if let Some(v) = target_id {
        obj.insert("target_id".into(), json!(v));
    }
    log_json(Value::Object(obj));
}

fn infer_route_context(path: &str) -> RouteContext {
    let parts: Vec<&str> = path.split('/').filter(|p| !p.is_empty()).collect();
    match parts.as_slice() {
        ["api", "health"] => RouteContext {
            route: "/api/health",
            ..RouteContext::default()
        },
        ["api", "resource-types"] => RouteContext {
            route: "/api/resource-types",
            ..RouteContext::default()
        },
        ["api", "resource-types", type_id] => RouteContext {
            route: "/api/resource-types/:type",
            target_type: Some((*type_id).to_string()),
            ..RouteContext::default()
        },
        ["api", "resources", type_id] => RouteContext {
            route: "/api/resources/:type",
            target_type: Some((*type_id).to_string()),
            ..RouteContext::default()
        },
        ["api", "resources", type_id, resource_id] => RouteContext {
            route: "/api/resources/:type/:id",
            target_type: Some((*type_id).to_string()),
            target_id: Some((*resource_id).to_string()),
        },
        ["api", "resources", type_id, resource_id, "references"] => RouteContext {
            route: "/api/resources/:type/:id/references",
            target_type: Some((*type_id).to_string()),
            target_id: Some((*resource_id).to_string()),
        },
        ["api", "validate"] => RouteContext {
            route: "/api/validate",
            ..RouteContext::default()
        },
        ["api", "validation"] => RouteContext {
            route: "/api/validation",
            ..RouteContext::default()
        },
        ["api", "import"] => RouteContext {
            route: "/api/import",
            ..RouteContext::default()
        },
        ["api", "import", "preview"] => RouteContext {
            route: "/api/import/preview",
            ..RouteContext::default()
        },
        ["api", "import", "apply"] => RouteContext {
            route: "/api/import/apply",
            ..RouteContext::default()
        },
        ["api", "export"] => RouteContext {
            route: "/api/export",
            ..RouteContext::default()
        },
        _ => RouteContext {
            route: "unknown",
            ..RouteContext::default()
        },
    }
}

async fn handle_api(
    req: &mut Request,
    api: &ApiService<D1Store>,
    path: &str,
    method: &Method,
) -> std::result::Result<HandledResponse, ApiError> {
    let parts: Vec<&str> = path.split('/').filter(|p| !p.is_empty()).collect();

    match (method.clone(), parts.as_slice()) {
        (Method::Get, ["api", "health"]) => {
            log_json(json!({
                "event": "api_health_check",
                "method": method.to_string(),
                "path": path,
                "route": "/api/health",
                "status": 200,
                "catalog_id": CATALOG_ID,
            }));
            Ok(HandledResponse {
                response: json_response(&json!({
                    "status": "ok",
                    "service": "cataloga",
                    "runtime": "cloudflare-worker",
                    "storage": "d1",
                    "catalog_id": CATALOG_ID,
                }))?,
                route: RouteContext {
                    route: "/api/health",
                    ..RouteContext::default()
                },
            })
        }
        (Method::Get, ["api", "resource-types"]) => Ok(HandledResponse {
            response: json_response(
                &api.list_resource_types(CATALOG_ID)
                    .await
                    .map_err(ApiError::from_service_error)?,
            )?,
            route: RouteContext {
                route: "/api/resource-types",
                ..RouteContext::default()
            },
        }),
        (Method::Post, ["api", "resource-types"]) | (Method::Put, ["api", "resource-types"]) => {
            let payload: ResourceType = req
                .json()
                .await
                .map_err(|e| ApiError::bad_request(e.to_string()))?;
            let target_type = payload.id.clone();
            api.create_or_update_resource_type(CATALOG_ID, payload)
                .await
                .map_err(ApiError::from_service_error)?;
            emit_operation_log("resource_type_upserted", Some(target_type.as_str()), None);
            Ok(HandledResponse {
                response: empty_response()?,
                route: RouteContext {
                    route: "/api/resource-types",
                    target_type: Some(target_type),
                    ..RouteContext::default()
                },
            })
        }
        (Method::Get, ["api", "resource-types", type_id]) => {
            let item = api
                .get_resource_type(CATALOG_ID, type_id)
                .await
                .map_err(ApiError::from_service_error)?;
            let item = item.ok_or_else(|| ApiError::not_found("resource type not found"))?;
            Ok(HandledResponse {
                response: json_response(&item)?,
                route: RouteContext {
                    route: "/api/resource-types/:type",
                    target_type: Some((*type_id).to_string()),
                    ..RouteContext::default()
                },
            })
        }
        (Method::Put, ["api", "resource-types", type_id]) => {
            let payload: ResourceType = req
                .json()
                .await
                .map_err(|e| ApiError::bad_request(e.to_string()))?;
            let target_type = if payload.id.is_empty() {
                (*type_id).to_string()
            } else {
                payload.id.clone()
            };
            api.create_or_update_resource_type(CATALOG_ID, payload)
                .await
                .map_err(ApiError::from_service_error)?;
            emit_operation_log("resource_type_upserted", Some(target_type.as_str()), None);
            Ok(HandledResponse {
                response: empty_response()?,
                route: RouteContext {
                    route: "/api/resource-types/:type",
                    target_type: Some(target_type),
                    ..RouteContext::default()
                },
            })
        }
        (Method::Delete, ["api", "resource-types", type_id]) => {
            api.delete_resource_type(CATALOG_ID, type_id)
                .await
                .map_err(ApiError::from_service_error)?;
            emit_operation_log("resource_type_deleted", Some(type_id), None);
            Ok(HandledResponse {
                response: empty_response()?,
                route: RouteContext {
                    route: "/api/resource-types/:type",
                    target_type: Some((*type_id).to_string()),
                    ..RouteContext::default()
                },
            })
        }
        (Method::Get, ["api", "resources", type_id]) => Ok(HandledResponse {
            response: json_response(
                &api.list_resources(CATALOG_ID, type_id)
                    .await
                    .map_err(ApiError::from_service_error)?,
            )?,
            route: RouteContext {
                route: "/api/resources/:type",
                target_type: Some((*type_id).to_string()),
                ..RouteContext::default()
            },
        }),
        (Method::Post, ["api", "resources", type_id])
        | (Method::Put, ["api", "resources", type_id]) => {
            let payload: Resource = req
                .json()
                .await
                .map_err(|e| ApiError::bad_request(e.to_string()))?;
            let target_type = if payload.resource_type.is_empty() {
                (*type_id).to_string()
            } else {
                payload.resource_type.clone()
            };
            let target_id = payload.id.clone();
            api.create_or_update_resource(CATALOG_ID, payload)
                .await
                .map_err(ApiError::from_service_error)?;
            emit_operation_log(
                "resource_upserted",
                Some(target_type.as_str()),
                Some(target_id.as_str()),
            );
            Ok(HandledResponse {
                response: empty_response()?,
                route: RouteContext {
                    route: "/api/resources/:type",
                    target_type: Some(target_type),
                    target_id: Some(target_id),
                },
            })
        }
        (Method::Get, ["api", "resources", type_id, resource_id]) => {
            let item = api
                .get_resource(CATALOG_ID, type_id, resource_id)
                .await
                .map_err(ApiError::from_service_error)?;
            let item = item.ok_or_else(|| ApiError::not_found("resource not found"))?;
            Ok(HandledResponse {
                response: json_response(&item)?,
                route: RouteContext {
                    route: "/api/resources/:type/:id",
                    target_type: Some((*type_id).to_string()),
                    target_id: Some((*resource_id).to_string()),
                },
            })
        }
        (Method::Get, ["api", "resources", type_id, resource_id, "references"]) => {
            Ok(HandledResponse {
                response: json_response(
                    &api.resource_references(CATALOG_ID, type_id, resource_id)
                        .await
                        .map_err(ApiError::from_service_error)?,
                )?,
                route: RouteContext {
                    route: "/api/resources/:type/:id/references",
                    target_type: Some((*type_id).to_string()),
                    target_id: Some((*resource_id).to_string()),
                },
            })
        }
        (Method::Put, ["api", "resources", type_id, resource_id]) => {
            let payload: Resource = req
                .json()
                .await
                .map_err(|e| ApiError::bad_request(e.to_string()))?;
            let target_type = if payload.resource_type.is_empty() {
                (*type_id).to_string()
            } else {
                payload.resource_type.clone()
            };
            let target_id = if payload.id.is_empty() {
                (*resource_id).to_string()
            } else {
                payload.id.clone()
            };
            api.create_or_update_resource(CATALOG_ID, payload)
                .await
                .map_err(ApiError::from_service_error)?;
            emit_operation_log(
                "resource_upserted",
                Some(target_type.as_str()),
                Some(target_id.as_str()),
            );
            Ok(HandledResponse {
                response: empty_response()?,
                route: RouteContext {
                    route: "/api/resources/:type/:id",
                    target_type: Some(target_type),
                    target_id: Some(target_id),
                },
            })
        }
        (Method::Delete, ["api", "resources", type_id, resource_id]) => {
            api.delete_resource(CATALOG_ID, type_id, resource_id)
                .await
                .map_err(ApiError::from_service_error)?;
            emit_operation_log("resource_deleted", Some(type_id), Some(resource_id));
            Ok(HandledResponse {
                response: empty_response()?,
                route: RouteContext {
                    route: "/api/resources/:type/:id",
                    target_type: Some((*type_id).to_string()),
                    target_id: Some((*resource_id).to_string()),
                },
            })
        }
        (Method::Post, ["api", "validate"]) => {
            api.validate_catalog(CATALOG_ID)
                .await
                .map_err(ApiError::from_service_error)?;
            emit_operation_log("validation_completed", None, None);
            Ok(HandledResponse {
                response: empty_response()?,
                route: RouteContext {
                    route: "/api/validate",
                    ..RouteContext::default()
                },
            })
        }
        (Method::Get, ["api", "validation"]) => {
            let result = api
                .validation_result(CATALOG_ID)
                .await
                .map_err(ApiError::from_service_error)?;
            emit_operation_log("validation_completed", None, None);
            Ok(HandledResponse {
                response: json_response(&result)?,
                route: RouteContext {
                    route: "/api/validation",
                    ..RouteContext::default()
                },
            })
        }
        (Method::Get, ["api", "export"]) => {
            let yaml = api
                .export_catalog_yaml(CATALOG_ID)
                .await
                .map_err(ApiError::from_service_error)?;

            let resource_types = api
                .list_resource_types(CATALOG_ID)
                .await
                .unwrap_or_default();
            let resource_type_count = resource_types.len();
            let mut resource_count = 0usize;
            for rt in &resource_types {
                if let Ok(items) = api.list_resources(CATALOG_ID, &rt.id).await {
                    resource_count += items.len();
                }
            }

            log_json(json!({
                "event": "export_completed",
                "catalog_id": CATALOG_ID,
                "resource_type_count": resource_type_count,
                "resource_count": resource_count,
            }));

            Ok(HandledResponse {
                response: text_response(yaml)?,
                route: RouteContext {
                    route: "/api/export",
                    ..RouteContext::default()
                },
            })
        }
        (Method::Post, ["api", "import"]) => {
            let payload: ImportRequest = req
                .json()
                .await
                .map_err(|e| ApiError::bad_request(e.to_string()))?;
            let preview = api
                .import_catalog_preview(CATALOG_ID, &payload.yaml)
                .await
                .map_err(ApiError::from_service_error)?;
            api.import_catalog_yaml(CATALOG_ID, &payload.yaml)
                .await
                .map_err(ApiError::from_service_error)?;
            log_json(json!({
                "event": "import_apply_completed",
                "catalog_id": CATALOG_ID,
                "resource_types_to_create": preview.resource_types_to_create,
                "resource_types_to_update": preview.resource_types_to_update,
                "resources_to_create": preview.resources_to_create,
                "resources_to_update": preview.resources_to_update,
            }));
            Ok(HandledResponse {
                response: empty_response()?,
                route: RouteContext {
                    route: "/api/import",
                    ..RouteContext::default()
                },
            })
        }
        (Method::Post, ["api", "import", "preview"]) => {
            let payload: ImportRequest = req
                .json()
                .await
                .map_err(|e| ApiError::bad_request(e.to_string()))?;
            let preview = api
                .import_catalog_preview(CATALOG_ID, &payload.yaml)
                .await
                .map_err(ApiError::from_service_error)?;
            log_json(json!({
                "event": "import_preview_completed",
                "catalog_id": CATALOG_ID,
                "resource_types_to_create": preview.resource_types_to_create,
                "resource_types_to_update": preview.resource_types_to_update,
                "resources_to_create": preview.resources_to_create,
                "resources_to_update": preview.resources_to_update,
            }));
            Ok(HandledResponse {
                response: json_response(&preview)?,
                route: RouteContext {
                    route: "/api/import/preview",
                    ..RouteContext::default()
                },
            })
        }
        (Method::Post, ["api", "import", "apply"]) => {
            let payload: ImportRequest = req
                .json()
                .await
                .map_err(|e| ApiError::bad_request(e.to_string()))?;
            let preview = api
                .import_catalog_preview(CATALOG_ID, &payload.yaml)
                .await
                .map_err(ApiError::from_service_error)?;
            api.import_catalog_yaml(CATALOG_ID, &payload.yaml)
                .await
                .map_err(ApiError::from_service_error)?;
            log_json(json!({
                "event": "import_apply_completed",
                "catalog_id": CATALOG_ID,
                "resource_types_to_create": preview.resource_types_to_create,
                "resource_types_to_update": preview.resource_types_to_update,
                "resources_to_create": preview.resources_to_create,
                "resources_to_update": preview.resources_to_update,
            }));
            Ok(HandledResponse {
                response: empty_response()?,
                route: RouteContext {
                    route: "/api/import/apply",
                    ..RouteContext::default()
                },
            })
        }
        _ => Err(ApiError::not_found("not found")),
    }
}

#[event(fetch)]
pub async fn fetch(mut req: Request, env: Env, _ctx: Context) -> Result<Response> {
    let method = req.method();
    let path = req.path();
    let start = js_sys::Date::now();
    let cf_ray = maybe_non_empty(req.headers().get("cf-ray").ok().flatten());

    let inferred_route = infer_route_context(&path);

    let response_pack = {
        let db = env.d1("CATALOGA_DB")?;
        let api = ApiService::new(D1Store::new(db));
        handle_api(&mut req, &api, &path, &method).await
    };

    let (response, route, err) = match response_pack {
        Ok(ok) => (ok.response, ok.route, None),
        Err(err) => {
            let response = match error_response(&err) {
                Ok(r) => r,
                Err(e) => Response::error(e.log_message, 500)?,
            };
            (response, inferred_route, Some(err))
        }
    };

    let duration_ms = (js_sys::Date::now() - start) as u64;
    let status = response.status_code();
    let cf_ray = cf_ray.as_deref();

    if let Some(err) = err {
        let event = match err.kind {
            ErrorKind::BadRequest => "api_request_bad_input",
            ErrorKind::NotFound => "api_request_not_found",
            ErrorKind::Validation | ErrorKind::Conflict | ErrorKind::Internal => {
                "api_request_failed"
            }
        };
        emit_request_log(RequestLog {
            event,
            method: &method,
            path: &path,
            route: route.route,
            status,
            duration_ms,
            cf_ray,
            target_type: route.target_type.as_deref(),
            target_id: route.target_id.as_deref(),
            error_kind: Some(err.kind.as_str()),
            error_message: Some(err.log_message.as_str()),
        });
    } else {
        emit_request_log(RequestLog {
            event: "api_request_completed",
            method: &method,
            path: &path,
            route: route.route,
            status,
            duration_ms,
            cf_ray,
            target_type: route.target_type.as_deref(),
            target_id: route.target_id.as_deref(),
            error_kind: None,
            error_message: None,
        });
    }

    Ok(response)
}

#[cfg(test)]
mod tests {
    use super::*;
    use anyhow::anyhow;
    use std::collections::HashSet;

    fn worker_route_set() -> HashSet<(ApiMethod, &'static str)> {
        HashSet::from([
            (ApiMethod::Get, "/api/health"),
            (ApiMethod::Get, "/api/resource-types"),
            (ApiMethod::Post, "/api/resource-types"),
            (ApiMethod::Get, "/api/resource-types/{type_id}"),
            (ApiMethod::Put, "/api/resource-types/{type_id}"),
            (ApiMethod::Delete, "/api/resource-types/{type_id}"),
            (ApiMethod::Get, "/api/resources/{type_id}"),
            (ApiMethod::Post, "/api/resources/{type_id}"),
            (ApiMethod::Get, "/api/resources/{type_id}/{resource_id}"),
            (ApiMethod::Put, "/api/resources/{type_id}/{resource_id}"),
            (ApiMethod::Delete, "/api/resources/{type_id}/{resource_id}"),
            (
                ApiMethod::Get,
                "/api/resources/{type_id}/{resource_id}/references",
            ),
            (ApiMethod::Post, "/api/validate"),
            (ApiMethod::Get, "/api/validation"),
            (ApiMethod::Post, "/api/import"),
            (ApiMethod::Post, "/api/import/preview"),
            (ApiMethod::Post, "/api/import/apply"),
            (ApiMethod::Get, "/api/export"),
        ])
    }

    #[test]
    fn worker_routes_match_canonical_api_routes() {
        let canonical: HashSet<(ApiMethod, &'static str)> = CANONICAL_API_ROUTES
            .iter()
            .map(|route| (route.method, route.path))
            .collect();
        assert_eq!(worker_route_set(), canonical);
    }

    #[test]
    fn service_conflict_maps_to_conflict_error_kind() {
        let err = anyhow!(ServiceError::Conflict(
            "resource type has existing resources and cannot be deleted".to_string()
        ));
        let api_err = ApiError::from_service_error(err);
        assert_eq!(api_err.kind.as_str(), "conflict");
        assert_eq!(api_err.kind.status(), 409);
    }
}
