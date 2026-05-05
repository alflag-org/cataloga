use axum::{
    Json, Router,
    extract::{Path, State},
    http::StatusCode,
    response::IntoResponse,
    routing::{get, post},
};
use cataloga_api::ApiService;
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
                .put(upsert_resource)
                .delete(delete_resource),
        )
        .route("/api/validate", post(validate_catalog))
        .route("/api/import", post(import_catalog))
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

async fn delete_resource(
    State(state): State<AppState>,
    Path((type_id, resource_id)): Path<(String, String)>,
) -> Result<StatusCode, AppError> {
    let api = build_api(&state);
    api.delete_resource(&state.catalog_id, &type_id, &resource_id)
        .await?;
    Ok(StatusCode::NO_CONTENT)
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
        Self {
            code: StatusCode::BAD_REQUEST,
            message: value.to_string(),
        }
    }
}

impl IntoResponse for AppError {
    fn into_response(self) -> axum::response::Response {
        (self.code, Json(json!({ "error": self.message }))).into_response()
    }
}
