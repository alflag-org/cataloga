import { useEffect, useMemo, useState } from "react";
import { api } from "../api/client";
import type { Resource, ResourceType } from "../types";
import { DataCard } from "./DataCard";
import { ErrorBanner } from "./ErrorBanner";
import { FieldInput } from "./FieldInput";
import { Button } from "./Button";
import { TextInput } from "./TextInput";
import { TextareaInput } from "./TextareaInput";

type Props = {
  resourceType: ResourceType;
  initial: Resource;
  mode: "create" | "edit";
  onSubmit: (resource: Resource) => Promise<void>;
};

function getReferenceForField(resourceType: ResourceType, fieldName: string) {
  return resourceType.references.find((r) => r.field === fieldName);
}

function parseFieldValue(type: string, raw: unknown): unknown {
  if (raw == null) return raw;
  if (type === "integer") {
    const n = Number(raw);
    return Number.isFinite(n) ? Math.trunc(n) : 0;
  }
  if (type === "number") {
    const n = Number(raw);
    return Number.isFinite(n) ? n : 0;
  }
  if (type === "boolean") return Boolean(raw);
  if (type === "array") {
    if (Array.isArray(raw)) return raw;
    const s = String(raw).trim();
    if (!s) return [];
    return JSON.parse(s);
  }
  if (type === "json") {
    if (typeof raw === "object") return raw;
    const s = String(raw).trim();
    if (!s) return {};
    return JSON.parse(s);
  }
  if (type === "reference_array") {
    if (Array.isArray(raw)) return raw.map(String);
    const s = String(raw).trim();
    if (!s) return [];
    try {
      return JSON.parse(s);
    } catch {
      return s
        .split("\n")
        .map((x) => x.trim())
        .filter(Boolean);
    }
  }
  return String(raw);
}

export function ResourceForm({ resourceType, initial, mode, onSubmit }: Props) {
  const [form, setForm] = useState<Resource>(initial);
  const [customFieldsText, setCustomFieldsText] = useState(
    JSON.stringify(initial.custom_fields ?? {}, null, 2),
  );
  const [dependenciesText, setDependenciesText] = useState(
    JSON.stringify(initial.dependencies ?? {}, null, 2),
  );
  const [error, setError] = useState<string | null>(null);
  const [referenceOptions, setReferenceOptions] = useState<
    Record<string, Array<{ id: string; name: string }>>
  >({});
  const required = useMemo(
    () => new Set(resourceType.required_fields),
    [resourceType.required_fields],
  );

  useEffect(() => {
    let alive = true;
    (async () => {
      try {
        const targetTypes = Array.from(
          new Set(resourceType.references.map((r) => r.target_type)),
        );
        const entries = await Promise.all(
          targetTypes.map(async (targetType) => {
            const items = await api.listResources(targetType);
            return [
              targetType,
              items.map((r) => ({ id: r.metadata.id, name: r.metadata.name })),
            ] as const;
          }),
        );
        if (alive) setReferenceOptions(Object.fromEntries(entries));
      } catch (e) {
        if (alive) setError(e instanceof Error ? e.message : String(e));
      }
    })();
    return () => {
      alive = false;
    };
  }, [resourceType.references]);

  const submit = async () => {
    try {
      setError(null);
      const next: Resource = {
        ...form,
        metadata: { ...form.metadata, type: resourceType.id },
        spec: { ...form.spec },
        custom_fields: JSON.parse(customFieldsText || "{}"),
        dependencies: JSON.parse(dependenciesText || "{}"),
      };
      for (const field of resourceType.fields) {
        const raw = next.spec[field.name];
        if (raw == null || raw === "") {
          if (required.has(field.name))
            throw new Error(`missing required field: ${field.name}`);
          delete next.spec[field.name];
          continue;
        }
        next.spec[field.name] = parseFieldValue(field.type, raw);
      }
      await onSubmit(next);
    } catch (e) {
      setError(e instanceof Error ? e.message : String(e));
    }
  };

  return (
    <div className="space-y-5">
      <ErrorBanner message={error} />
      <DataCard title="Metadata">
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <label className="block text-sm font-medium text-gray-700">
            ID
            <TextInput
              value={form.metadata.id}
              disabled={mode === "edit"}
              onChange={(e) =>
                setForm({
                  ...form,
                  metadata: { ...form.metadata, id: e.target.value },
                })
              }
            />
          </label>
          <label className="block text-sm font-medium text-gray-700">
            Name
            <TextInput
              value={form.metadata.name}
              onChange={(e) =>
                setForm({
                  ...form,
                  metadata: { ...form.metadata, name: e.target.value },
                })
              }
            />
          </label>
        </div>
      </DataCard>
      <DataCard title="Fields">
        <div className="space-y-4">
          {resourceType.fields.map((field) => (
            <label
              key={field.name}
              className="block text-sm font-medium text-gray-700"
            >
              {field.label || field.name}
              {(() => {
                const reference = getReferenceForField(
                  resourceType,
                  field.name,
                );
                const options = reference
                  ? (referenceOptions[reference.target_type] ?? [])
                  : [];
                return (
                  <FieldInput
                    field={field}
                    value={form.spec[field.name]}
                    reference={
                      reference
                        ? {
                            multiple: reference.multiple,
                            options,
                          }
                        : undefined
                    }
                    onChange={(value) =>
                      setForm({
                        ...form,
                        spec: {
                          ...form.spec,
                          [field.name]: value,
                        },
                      })
                    }
                  />
                );
              })()}
            </label>
          ))}
        </div>
      </DataCard>
      <DataCard title="Advanced">
        <div className="grid grid-cols-1 gap-4">
          <label className="block text-sm font-medium text-gray-700">
            custom_fields JSON
            <TextareaInput
              rows={5}
              value={customFieldsText}
              onChange={(e) => setCustomFieldsText(e.target.value)}
            />
          </label>
          <label className="block text-sm font-medium text-gray-700">
            dependencies JSON
            <TextareaInput
              rows={5}
              value={dependenciesText}
              onChange={(e) => setDependenciesText(e.target.value)}
            />
          </label>
        </div>
      </DataCard>
      <div className="flex justify-end">
        <Button onClick={submit}>Save</Button>
      </div>
    </div>
  );
}
