use axum::{
    Json, Router,
    extract::{Path, State},
    http::StatusCode,
    response::IntoResponse,
    routing::{get, post},
};
use cataloga_api::{
    ApiError as ServiceError, ApiService, ImportPreviewResult, ResourceReferences, ValidationResult,
};
#[cfg(test)]
use cataloga_api::{ApiMethod, CANONICAL_API_ROUTES};
use cataloga_core::{Resource, ResourceType};
use cataloga_store_sqlite::SqliteStore;
use serde::Deserialize;
use serde_json::json;
use std::{net::SocketAddr, sync::Arc};

#[derive(Clone)]
struct AppState {
    store: Arc<SqliteStore>,
    catalog_id: String,
}

#[derive(Deserialize)]
struct ImportRequest {
    yaml: String,
}

pub async fn serve(db_url: String, listen: SocketAddr, catalog_id: String) -> anyhow::Result<()> {
    let store = Arc::new(SqliteStore::connect(&db_url).await?);

    let app = Router::new()
        .route(
            "/api/resource-types",
            get(list_resource_types).post(upsert_resource_type),
        )
        .route(
            "/api/resource-types/{type_id}",
            get(get_resource_type)
                .put(upsert_resource_type)
                .delete(delete_resource_type),
        )
        .route(
            "/api/resources/{type_id}",
            get(list_resources).post(upsert_resource),
        )
        .route(
            "/api/resources/{type_id}/{resource_id}",
            get(get_resource)
                .put(update_resource)
                .delete(delete_resource),
        )
        .route(
            "/api/resources/{type_id}/{resource_id}/references",
            get(get_resource_references),
        )
        .route("/api/validate", post(validate_catalog))
        .route("/api/validation", get(validation_result))
        .route("/api/import", post(import_catalog))
        .route("/api/import/preview", post(import_preview))
        .route("/api/import/apply", post(import_apply))
        .route("/api/export", get(export_catalog))
        .route("/api/health", get(health))
        .with_state(AppState { store, catalog_id });

    let listener = tokio::net::TcpListener::bind(listen).await?;
    axum::serve(listener, app).await?;
    Ok(())
}

async fn health() -> impl IntoResponse {
    Json(json!({ "status": "ok" }))
}

async fn list_resource_types(
    State(state): State<AppState>,
) -> Result<Json<Vec<ResourceType>>, AppError> {
    let api = build_api(&state);
    Ok(Json(api.list_resource_types(&state.catalog_id).await?))
}

async fn get_resource_type(
    State(state): State<AppState>,
    Path(type_id): Path<String>,
) -> Result<Json<ResourceType>, AppError> {
    let api = build_api(&state);
    let item = api
        .get_resource_type(&state.catalog_id, &type_id)
        .await?
        .ok_or_else(|| AppError::new(StatusCode::NOT_FOUND, "resource type not found"))?;
    Ok(Json(item))
}

async fn upsert_resource_type(
    State(state): State<AppState>,
    Json(payload): Json<ResourceType>,
) -> Result<StatusCode, AppError> {
    let api = build_api(&state);
    api.create_or_update_resource_type(&state.catalog_id, payload)
        .await?;
    Ok(StatusCode::NO_CONTENT)
}

async fn delete_resource_type(
    State(state): State<AppState>,
    Path(type_id): Path<String>,
) -> Result<StatusCode, AppError> {
    let api = build_api(&state);
    api.delete_resource_type(&state.catalog_id, &type_id)
        .await?;
    Ok(StatusCode::NO_CONTENT)
}

async fn list_resources(
    State(state): State<AppState>,
    Path(type_id): Path<String>,
) -> Result<Json<Vec<Resource>>, AppError> {
    let api = build_api(&state);
    Ok(Json(api.list_resources(&state.catalog_id, &type_id).await?))
}

async fn get_resource(
    State(state): State<AppState>,
    Path((type_id, resource_id)): Path<(String, String)>,
) -> Result<Json<Resource>, AppError> {
    let api = build_api(&state);
    let item = api
        .get_resource(&state.catalog_id, &type_id, &resource_id)
        .await?
        .ok_or_else(|| AppError::new(StatusCode::NOT_FOUND, "resource not found"))?;
    Ok(Json(item))
}

async fn upsert_resource(
    State(state): State<AppState>,
    Json(payload): Json<Resource>,
) -> Result<StatusCode, AppError> {
    let api = build_api(&state);
    api.create_or_update_resource(&state.catalog_id, payload)
        .await?;
    Ok(StatusCode::NO_CONTENT)
}

async fn update_resource(
    State(state): State<AppState>,
    Path((type_id, resource_id)): Path<(String, String)>,
    Json(payload): Json<Resource>,
) -> Result<StatusCode, AppError> {
    let api = build_api(&state);
    api.update_resource(&state.catalog_id, &type_id, &resource_id, payload)
        .await?;
    Ok(StatusCode::NO_CONTENT)
}

async fn delete_resource(
    State(state): State<AppState>,
    Path((type_id, resource_id)): Path<(String, String)>,
) -> Result<StatusCode, AppError> {
    let api = build_api(&state);
    api.delete_resource(&state.catalog_id, &type_id, &resource_id)
        .await?;
    Ok(StatusCode::NO_CONTENT)
}

async fn get_resource_references(
    State(state): State<AppState>,
    Path((type_id, resource_id)): Path<(String, String)>,
) -> Result<Json<ResourceReferences>, AppError> {
    let api = build_api(&state);
    Ok(Json(
        api.resource_references(&state.catalog_id, &type_id, &resource_id)
            .await?,
    ))
}

async fn validate_catalog(State(state): State<AppState>) -> Result<StatusCode, AppError> {
    let api = build_api(&state);
    api.validate_catalog(&state.catalog_id).await?;
    Ok(StatusCode::NO_CONTENT)
}

async fn import_catalog(
    State(state): State<AppState>,
    Json(payload): Json<ImportRequest>,
) -> Result<StatusCode, AppError> {
    let api = build_api(&state);
    api.import_catalog_yaml(&state.catalog_id, &payload.yaml)
        .await?;
    Ok(StatusCode::NO_CONTENT)
}

async fn validation_result(
    State(state): State<AppState>,
) -> Result<Json<ValidationResult>, AppError> {
    let api = build_api(&state);
    Ok(Json(api.validation_result(&state.catalog_id).await?))
}

async fn import_preview(
    State(state): State<AppState>,
    Json(payload): Json<ImportRequest>,
) -> Result<Json<ImportPreviewResult>, AppError> {
    let api = build_api(&state);
    Ok(Json(
        api.import_catalog_preview(&state.catalog_id, &payload.yaml)
            .await?,
    ))
}

async fn import_apply(
    State(state): State<AppState>,
    Json(payload): Json<ImportRequest>,
) -> Result<StatusCode, AppError> {
    let api = build_api(&state);
    api.import_catalog_yaml(&state.catalog_id, &payload.yaml)
        .await?;
    Ok(StatusCode::NO_CONTENT)
}

async fn export_catalog(State(state): State<AppState>) -> Result<String, AppError> {
    let api = build_api(&state);
    Ok(api.export_catalog_yaml(&state.catalog_id).await?)
}

fn build_api(state: &AppState) -> ApiService<Arc<SqliteStore>> {
    ApiService::new(state.store.clone())
}

#[derive(Debug)]
struct AppError {
    code: StatusCode,
    message: String,
}

impl AppError {
    fn new(code: StatusCode, message: &str) -> Self {
        Self {
            code,
            message: message.to_string(),
        }
    }
}

impl From<anyhow::Error> for AppError {
    fn from(value: anyhow::Error) -> Self {
        if let Some(service_error) = value.downcast_ref::<ServiceError>() {
            let (code, message) = match service_error {
                ServiceError::NotFound(msg) => (StatusCode::NOT_FOUND, msg.clone()),
                ServiceError::Validation(msg) => (StatusCode::BAD_REQUEST, msg.clone()),
                ServiceError::Conflict(msg) => (StatusCode::CONFLICT, msg.clone()),
                ServiceError::BadRequest(msg) => (StatusCode::BAD_REQUEST, msg.clone()),
                ServiceError::Internal(msg) => (StatusCode::INTERNAL_SERVER_ERROR, msg.clone()),
            };
            return Self { code, message };
        }
        Self {
            code: StatusCode::INTERNAL_SERVER_ERROR,
            message: value.to_string(),
        }
    }
}

impl IntoResponse for AppError {
    fn into_response(self) -> axum::response::Response {
        (self.code, Json(json!({ "error": self.message }))).into_response()
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use std::collections::HashSet;

    fn server_route_set() -> HashSet<(ApiMethod, &'static str)> {
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
    fn server_routes_match_canonical_api_routes() {
        let canonical: HashSet<(ApiMethod, &'static str)> = CANONICAL_API_ROUTES
            .iter()
            .map(|route| (route.method, route.path))
            .collect();
        assert_eq!(server_route_set(), canonical);
    }
}
