import { type ReactNode, useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { api } from "../api/client";
import { ActionButton, ActionLink } from "../components/Action";
import { DataCard } from "../components/DataCard";
import { ErrorBanner } from "../components/ErrorBanner";
import { PageHeader } from "../components/PageHeader";
import {
  deriveDisplayLabel,
  type FieldDef,
  type Resource,
  type ResourceReferences,
  type ResourceType,
} from "../types";

function renderValue(value: unknown, field?: FieldDef): ReactNode {
  const type = field?.type;
  if (type === "boolean") {
    return <span className="text-sm">{Boolean(value) ? "True" : "False"}</span>;
  }
  if (type === "array" || Array.isArray(value)) {
    const items = Array.isArray(value) ? value : [];
    return (
      <ul className="list-disc pl-5 text-sm text-gray-700">
        {items.map((item, idx) => (
          <li key={idx}>{String(item)}</li>
        ))}
      </ul>
    );
  }
  if (type === "json" && value && typeof value === "object") {
    return (
      <pre className="rounded bg-gray-50 p-2 text-xs text-gray-700">
        {JSON.stringify(value, null, 2)}
      </pre>
    );
  }
  if (type === "url" && typeof value === "string" && value.startsWith("http")) {
    return (
      <a
        className="text-blue-700 hover:text-blue-800"
        href={value}
        target="_blank"
        rel="noreferrer"
      >
        {value}
      </a>
    );
  }
  return (
    <span className="text-sm text-gray-700">
      {value == null ? "" : String(value)}
    </span>
  );
}

export function ResourceDetailPage() {
  const { type = "", id = "" } = useParams();
  const navigate = useNavigate();
  const [resource, setResource] = useState<Resource | null>(null);
  const [resourceType, setResourceType] = useState<ResourceType | null>(null);
  const [references, setReferences] = useState<ResourceReferences | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api
      .getResource(type, id)
      .then(setResource)
      .catch((e) => setError(e.message));
    api
      .getResourceType(type)
      .then(setResourceType)
      .catch((e) => setError(e.message));
    api
      .getResourceReferences(type, id)
      .then(setReferences)
      .catch((e) => setError(e.message));
  }, [id, type]);

  const fieldMap = useMemo(() => {
    const map = new Map<string, FieldDef>();
    for (const field of resourceType?.fields ?? []) map.set(field.name, field);
    return map;
  }, [resourceType?.fields]);

  return (
    <section className="space-y-5">
      <PageHeader
        title={`Resources / ${type} / ${id}`}
        actions={
          <div className="flex items-center gap-3">
            <ActionLink to={`/resources/${type}/${id}/edit`}>Edit</ActionLink>
            <ActionButton
              tone="danger"
              onClick={async () => {
                if (!window.confirm(`Delete Resource '${type}/${id}'?`)) return;
                try {
                  await api.deleteResource(type, id);
                  navigate(`/resources/${type}`);
                } catch (e) {
                  setError(e instanceof Error ? e.message : String(e));
                }
              }}
            >
              Delete
            </ActionButton>
          </div>
        }
      />
      <ErrorBanner message={error} />

      {resource ? (
        <>
          <DataCard title="Metadata">
            <dl className="grid grid-cols-[140px_1fr] gap-y-2 text-sm">
              <dt className="font-medium text-gray-600">ID</dt>
              <dd>{resource.metadata.id}</dd>
              <dt className="font-medium text-gray-600">Type</dt>
              <dd>{resource.metadata.type}</dd>
              <dt className="font-medium text-gray-600">Name</dt>
              <dd>{resource.metadata.name}</dd>
              <dt className="font-medium text-gray-600">Tags</dt>
              <dd>
                {Object.entries(resource.metadata.tags || {})
                  .map(([k, v]) => `${k}=${v}`)
                  .join(", ") || "-"}
              </dd>
            </dl>
          </DataCard>

          <DataCard title="Spec">
            {Object.keys(resource.spec).length === 0 ? (
              <p className="text-sm text-gray-600">No spec fields.</p>
            ) : (
              <div className="space-y-3">
                {Object.entries(resource.spec).map(([key, value]) => {
                  const field = fieldMap.get(key);
                  return (
                    <div
                      key={key}
                      className="grid grid-cols-[180px_1fr] items-start gap-2"
                    >
                      <p className="text-sm font-medium text-gray-700">
                        {field?.label || deriveDisplayLabel(`spec.${key}`)}
                      </p>
                      <div>{renderValue(value, field)}</div>
                    </div>
                  );
                })}
              </div>
            )}
          </DataCard>

          <DataCard title="Referenced resources">
            {references && references.outgoing.length > 0 ? (
              <div className="space-y-2 text-sm">
                {references.outgoing.map((item, idx) => (
                  <div
                    key={`${item.resource_type}-${item.resource_id}-${idx}`}
                    className="grid grid-cols-[180px_1fr] gap-2"
                  >
                    <span className="text-gray-700">
                      {deriveDisplayLabel(`spec.${item.field}`)}
                    </span>
                    <ActionLink
                      tone="primary"
                      className="underline underline-offset-2"
                      to={`/resources/${item.resource_type}/${item.resource_id}`}
                    >
                      {item.resource_type} / {item.name} ({item.resource_id})
                    </ActionLink>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-sm text-gray-600">No referenced resources.</p>
            )}
          </DataCard>

          <DataCard title="Used by">
            {references && references.incoming.length > 0 ? (
              <div className="space-y-2 text-sm">
                {references.incoming.map((item, idx) => (
                  <div
                    key={`${item.resource_type}-${item.resource_id}-${idx}`}
                    className="grid grid-cols-[180px_1fr] gap-2"
                  >
                    <span className="text-gray-700">
                      {deriveDisplayLabel(`spec.${item.field}`)}
                    </span>
                    <ActionLink
                      tone="primary"
                      className="underline underline-offset-2"
                      to={`/resources/${item.resource_type}/${item.resource_id}`}
                    >
                      {item.resource_type} / {item.name} ({item.resource_id})
                    </ActionLink>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-sm text-gray-600">
                No resources reference this resource.
              </p>
            )}
          </DataCard>

          {Object.keys(resource.custom_fields || {}).length > 0 ? (
            <DataCard title="Custom fields">
              <pre className="overflow-x-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-700">
                {JSON.stringify(resource.custom_fields, null, 2)}
              </pre>
            </DataCard>
          ) : null}

          {Object.keys(resource.dependencies || {}).length > 0 ? (
            <DataCard title="Dependencies">
              <pre className="overflow-x-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-700">
                {JSON.stringify(resource.dependencies, null, 2)}
              </pre>
            </DataCard>
          ) : null}

          <details className="rounded-xl border border-gray-200 bg-white p-4">
            <summary className="cursor-pointer text-sm font-medium text-gray-700">
              Raw JSON
            </summary>
            <pre className="mt-3 overflow-x-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">
              {JSON.stringify(resource, null, 2)}
            </pre>
          </details>
        </>
      ) : null}
    </section>
  );
}
