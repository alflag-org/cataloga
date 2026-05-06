use cataloga_api::ApiService;
use cataloga_core::{Resource, ResourceType};
use cataloga_store_d1::D1Store;
use serde::Deserialize;
use worker::*;

const CATALOG_ID: &str = "default";

#[derive(Deserialize)]
struct ImportRequest {
    yaml: String,
}

fn json_response<T: serde::Serialize>(value: &T) -> Result<Response> {
    Response::from_json(value)
}

#[event(fetch)]
pub async fn fetch(mut req: Request, env: Env, _ctx: Context) -> Result<Response> {
    if req.path() == "/api/health" && req.method() == Method::Get {
        return json_response(&serde_json::json!({ "status": "ok" }));
    }

    let db = env.d1("CATALOGA_DB")?;
    let api = ApiService::new(D1Store::new(db));

    let path = req.path();
    let method = req.method();
    let parts: Vec<&str> = path.split('/').filter(|p| !p.is_empty()).collect();

    match (method, parts.as_slice()) {
        (Method::Get, ["api", "resource-types"]) => {
            let items = api
                .list_resource_types(CATALOG_ID)
                .await
                .map_err(|e| Error::RustError(e.to_string()))?;
            json_response(&items)
        }
        (Method::Post, ["api", "resource-types"]) | (Method::Put, ["api", "resource-types"]) => {
            let payload: ResourceType = req
                .json()
                .await
                .map_err(|e| Error::RustError(e.to_string()))?;
            api.create_or_update_resource_type(CATALOG_ID, payload)
                .await
                .map_err(|e| Error::RustError(e.to_string()))?;
            Response::empty()
        }
        (Method::Get, ["api", "resource-types", type_id]) => {
            let item = api
                .get_resource_type(CATALOG_ID, type_id)
                .await
                .map_err(|e| Error::RustError(e.to_string()))?;
            match item {
                Some(item) => json_response(&item),
                None => Response::error("resource type not found", 404),
            }
        }
        (Method::Put, ["api", "resource-types", _type_id]) => {
            let payload: ResourceType = req
                .json()
                .await
                .map_err(|e| Error::RustError(e.to_string()))?;
            api.create_or_update_resource_type(CATALOG_ID, payload)
                .await
                .map_err(|e| Error::RustError(e.to_string()))?;
            Response::empty()
        }
        (Method::Delete, ["api", "resource-types", type_id]) => {
            api.delete_resource_type(CATALOG_ID, type_id)
                .await
                .map_err(|e| Error::RustError(e.to_string()))?;
            Response::empty()
        }
        (Method::Get, ["api", "resources", type_id]) => {
            let items = api
                .list_resources(CATALOG_ID, type_id)
                .await
                .map_err(|e| Error::RustError(e.to_string()))?;
            json_response(&items)
        }
        (Method::Post, ["api", "resources", _type_id])
        | (Method::Put, ["api", "resources", _type_id]) => {
            let payload: Resource = req
                .json()
                .await
                .map_err(|e| Error::RustError(e.to_string()))?;
            api.create_or_update_resource(CATALOG_ID, payload)
                .await
                .map_err(|e| Error::RustError(e.to_string()))?;
            Response::empty()
        }
        (Method::Get, ["api", "resources", type_id, resource_id]) => {
            let item = api
                .get_resource(CATALOG_ID, type_id, resource_id)
                .await
                .map_err(|e| Error::RustError(e.to_string()))?;
            match item {
                Some(item) => json_response(&item),
                None => Response::error("resource not found", 404),
            }
        }
        (Method::Get, ["api", "resources", type_id, resource_id, "references"]) => {
            let refs = api
                .resource_references(CATALOG_ID, type_id, resource_id)
                .await
                .map_err(|e| Error::RustError(e.to_string()))?;
            json_response(&refs)
        }
        (Method::Put, ["api", "resources", _type_id, _resource_id]) => {
            let payload: Resource = req
                .json()
                .await
                .map_err(|e| Error::RustError(e.to_string()))?;
            api.create_or_update_resource(CATALOG_ID, payload)
                .await
                .map_err(|e| Error::RustError(e.to_string()))?;
            Response::empty()
        }
        (Method::Delete, ["api", "resources", type_id, resource_id]) => {
            api.delete_resource(CATALOG_ID, type_id, resource_id)
                .await
                .map_err(|e| Error::RustError(e.to_string()))?;
            Response::empty()
        }
        (Method::Post, ["api", "validate"]) => {
            api.validate_catalog(CATALOG_ID)
                .await
                .map_err(|e| Error::RustError(e.to_string()))?;
            Response::empty()
        }
        (Method::Get, ["api", "validation"]) => {
            let result = api
                .validation_result(CATALOG_ID)
                .await
                .map_err(|e| Error::RustError(e.to_string()))?;
            json_response(&result)
        }
        (Method::Get, ["api", "export"]) => {
            let yaml = api
                .export_catalog_yaml(CATALOG_ID)
                .await
                .map_err(|e| Error::RustError(e.to_string()))?;
            Response::ok(yaml)
        }
        (Method::Post, ["api", "import"]) => {
            let payload: ImportRequest = req
                .json()
                .await
                .map_err(|e| Error::RustError(e.to_string()))?;
            api.import_catalog_yaml(CATALOG_ID, &payload.yaml)
                .await
                .map_err(|e| Error::RustError(e.to_string()))?;
            Response::empty()
        }
        (Method::Post, ["api", "import", "preview"]) => {
            let payload: ImportRequest = req
                .json()
                .await
                .map_err(|e| Error::RustError(e.to_string()))?;
            let preview = api
                .import_catalog_preview(CATALOG_ID, &payload.yaml)
                .await
                .map_err(|e| Error::RustError(e.to_string()))?;
            json_response(&preview)
        }
        (Method::Post, ["api", "import", "apply"]) => {
            let payload: ImportRequest = req
                .json()
                .await
                .map_err(|e| Error::RustError(e.to_string()))?;
            api.import_catalog_yaml(CATALOG_ID, &payload.yaml)
                .await
                .map_err(|e| Error::RustError(e.to_string()))?;
            Response::empty()
        }
        _ => Response::error("not found", 404),
    }
}
