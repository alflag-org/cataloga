import { useEffect, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { api } from "../api/client";
import { ActionButton } from "../components/Action";
import { Button } from "../components/Button";
import { DataCard } from "../components/DataCard";
import { ErrorBanner } from "../components/ErrorBanner";
import { PageHeader } from "../components/PageHeader";
import { SelectInput } from "../components/SelectInput";
import { TextInput } from "../components/TextInput";
import { TextareaInput } from "../components/TextareaInput";
import {
  defaultResourceType,
  deriveDisplayLabel,
  normalizeListColumns,
  type FieldDef,
  type ResourceType,
} from "../types";

function parseJsonArray(text: string): unknown[] {
  const trimmed = text.trim();
  if (!trimmed) return [];
  return JSON.parse(trimmed);
}

const fieldTypes: FieldDef["type"][] = [
  "string",
  "text",
  "integer",
  "number",
  "boolean",
  "enum",
  "array",
  "json",
  "reference",
  "reference_array",
  "ip",
  "cidr",
  "url",
];

export function ResourceTypeEditorPage({ mode }: { mode: "create" | "edit" }) {
  const { type = "" } = useParams();
  const navigate = useNavigate();
  const [value, setValue] = useState<ResourceType>(defaultResourceType());
  const [allTypes, setAllTypes] = useState<ResourceType[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [advanced, setAdvanced] = useState({
    form_layout: "[]",
    detail_sections: "[]",
    validation_rules: "[]",
  });

  useEffect(() => {
    api
      .listResourceTypes()
      .then(setAllTypes)
      .catch((e) => setError(e.message));
    if (mode === "edit" && type) {
      api
        .getResourceType(type)
        .then((rt) => {
          setValue(rt);
          setAdvanced({
            form_layout: JSON.stringify(rt.form_layout ?? [], null, 2),
            detail_sections: JSON.stringify(rt.detail_sections ?? [], null, 2),
            validation_rules: JSON.stringify(
              rt.validation_rules ?? [],
              null,
              2,
            ),
          });
        })
        .catch((e) => setError(e.message));
    }
  }, [mode, type]);

  const upsertField = (idx: number, next: FieldDef) => {
    const fields = [...value.fields];
    fields[idx] = next;
    setValue({ ...value, fields });
  };

  const removeField = (idx: number) => {
    const target = value.fields[idx]?.name;
    const fields = value.fields.filter((_, i) => i !== idx);
    const references = target
      ? value.references.filter((ref) => ref.field !== target)
      : value.references;
    setValue({ ...value, fields, references });
  };

  const setFieldName = (idx: number, name: string) => {
    const current = value.fields[idx];
    const oldName = current.name;
    const autoLabel =
      current.label.trim() === "" ||
      current.label === deriveDisplayLabel(oldName);
    const nextLabel = autoLabel ? deriveDisplayLabel(name) : current.label;
    upsertField(idx, { ...current, name, label: nextLabel });

    if (oldName && oldName !== name) {
      const references = value.references.map((ref) =>
        ref.field === oldName ? { ...ref, field: name } : ref,
      );
      setValue((prev) => ({ ...prev, references }));
    }
  };

  const toggleRequired = (fieldName: string, checked: boolean) => {
    const set = new Set(value.required_fields);
    if (checked) set.add(fieldName);
    else set.delete(fieldName);
    setValue({ ...value, required_fields: [...set] });
  };

  const save = async () => {
    try {
      setError(null);
      const payload: ResourceType = {
        ...value,
        fields: value.fields.filter((field) => field.name.trim()),
        required_fields: value.required_fields.filter(Boolean),
        list_columns: normalizeListColumns(value.list_columns).map((col) => ({
          path: col.path,
          label: col.label,
        })),
        references: value.references.filter(
          (ref) => ref.field && ref.target_type,
        ),
        form_layout: parseJsonArray(
          advanced.form_layout,
        ) as ResourceType["form_layout"],
        detail_sections: parseJsonArray(
          advanced.detail_sections,
        ) as ResourceType["detail_sections"],
        validation_rules: parseJsonArray(
          advanced.validation_rules,
        ) as ResourceType["validation_rules"],
      };
      if (mode === "create") await api.upsertResourceType(payload);
      else await api.updateResourceType(type, payload);
      navigate("/resource-types");
    } catch (e) {
      setError(e instanceof Error ? e.message : String(e));
    }
  };

  return (
    <section className="space-y-5">
      <PageHeader
        title={
          mode === "create"
            ? "Administration / Resource Types / Create Resource Type"
            : `Administration / Resource Types / ${type} / Edit schema`
        }
      />
      <ErrorBanner message={error} />

      <DataCard title="General">
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <label className="block text-sm font-medium text-gray-700">
            ID
            <TextInput
              value={value.id}
              disabled={mode === "edit"}
              onChange={(e) => setValue({ ...value, id: e.target.value })}
            />
          </label>
          <label className="block text-sm font-medium text-gray-700">
            Title
            <TextInput
              value={value.title}
              onChange={(e) => setValue({ ...value, title: e.target.value })}
            />
          </label>
          <label className="block text-sm font-medium text-gray-700">
            Group
            <TextInput
              value={value.group}
              onChange={(e) => setValue({ ...value, group: e.target.value })}
            />
          </label>
          <label className="block text-sm font-medium text-gray-700 md:col-span-2">
            Description
            <TextareaInput
              rows={3}
              value={value.description}
              onChange={(e) =>
                setValue({ ...value, description: e.target.value })
              }
            />
          </label>
        </div>
      </DataCard>

      <DataCard
        title="Fields"
        actions={
          <div className="flex items-center gap-3">
            <Link
              to="/field-types"
              className="text-sm font-medium text-blue-700 hover:text-blue-800"
            >
              Field Types guide
            </Link>
            <Button
              variant="secondary"
              onClick={() =>
                setValue({
                  ...value,
                  fields: [
                    ...value.fields,
                    { name: "", label: "", type: "string", enum_values: [] },
                  ],
                })
              }
            >
              Add field
            </Button>
          </div>
        }
      >
        <div className="space-y-3">
          {value.fields.map((field, idx) => (
            <div
              key={`${field.name}-${idx}`}
              className="rounded-lg border border-gray-200 p-3"
            >
              <div className="grid grid-cols-1 gap-3 md:grid-cols-5">
                <label className="text-sm text-gray-700">
                  Field name
                  <TextInput
                    value={field.name}
                    onChange={(e) => setFieldName(idx, e.target.value)}
                    placeholder="primary_ip"
                  />
                </label>
                <label className="text-sm text-gray-700">
                  Label
                  <TextInput
                    value={field.label}
                    onChange={(e) =>
                      upsertField(idx, { ...field, label: e.target.value })
                    }
                    placeholder="Primary IP"
                  />
                </label>
                <label className="text-sm text-gray-700">
                  Type
                  <SelectInput
                    value={field.type}
                    onChange={(e) =>
                      upsertField(idx, {
                        ...field,
                        type: e.target.value as FieldDef["type"],
                      })
                    }
                  >
                    {fieldTypes.map((item) => (
                      <option key={item} value={item}>
                        {item}
                      </option>
                    ))}
                  </SelectInput>
                </label>
                <label className="text-sm text-gray-700">
                  Required
                  <SelectInput
                    value={
                      value.required_fields.includes(field.name) ? "yes" : "no"
                    }
                    onChange={(e) =>
                      toggleRequired(field.name, e.target.value === "yes")
                    }
                  >
                    <option value="no">No</option>
                    <option value="yes">Yes</option>
                  </SelectInput>
                </label>
                <div className="flex items-end">
                  <ActionButton tone="danger" onClick={() => removeField(idx)}>
                    Remove
                  </ActionButton>
                </div>
              </div>

              {field.type === "enum" ? (
                <div className="mt-3">
                  <p className="text-sm font-medium text-gray-700">
                    Enum values
                  </p>
                  <div className="mt-2 space-y-2">
                    {(field.enum_values ?? []).map((valueText, enumIndex) => (
                      <div
                        key={`${valueText}-${enumIndex}`}
                        className="flex items-center gap-2"
                      >
                        <TextInput
                          value={valueText}
                          onChange={(e) => {
                            const enumValues = [...field.enum_values];
                            enumValues[enumIndex] = e.target.value;
                            upsertField(idx, {
                              ...field,
                              enum_values: enumValues.filter(Boolean),
                            });
                          }}
                        />
                        <ActionButton
                          tone="danger"
                          onClick={() => {
                            const enumValues = field.enum_values.filter(
                              (_, i) => i !== enumIndex,
                            );
                            upsertField(idx, {
                              ...field,
                              enum_values: enumValues,
                            });
                          }}
                        >
                          Remove
                        </ActionButton>
                      </div>
                    ))}
                    <Button
                      variant="secondary"
                      onClick={() =>
                        upsertField(idx, {
                          ...field,
                          enum_values: [...field.enum_values, ""],
                        })
                      }
                    >
                      Add value
                    </Button>
                  </div>
                </div>
              ) : null}
            </div>
          ))}
        </div>
      </DataCard>

      <DataCard title="List columns">
        <div className="space-y-3">
          {normalizeListColumns(value.list_columns).map((column, idx) => (
            <div
              key={`${column.path}-${idx}`}
              className="grid grid-cols-1 gap-3 md:grid-cols-[1fr_1fr_auto] md:items-end"
            >
              <label className="text-sm text-gray-700">
                Path
                <TextInput
                  value={column.path}
                  onChange={(e) => {
                    const next = [...normalizeListColumns(value.list_columns)];
                    next[idx] = { ...next[idx], path: e.target.value };
                    setValue({ ...value, list_columns: next });
                  }}
                />
              </label>
              <label className="text-sm text-gray-700">
                Label
                <TextInput
                  value={column.label}
                  onChange={(e) => {
                    const next = [...normalizeListColumns(value.list_columns)];
                    next[idx] = { ...next[idx], label: e.target.value };
                    setValue({ ...value, list_columns: next });
                  }}
                />
              </label>
              <ActionButton
                tone="danger"
                onClick={() => {
                  const next = normalizeListColumns(value.list_columns).filter(
                    (_, i) => i !== idx,
                  );
                  setValue({ ...value, list_columns: next });
                }}
              >
                Remove
              </ActionButton>
            </div>
          ))}
          <Button
            variant="secondary"
            onClick={() =>
              setValue({
                ...value,
                list_columns: [
                  ...normalizeListColumns(value.list_columns),
                  { path: "metadata.name", label: "Name" },
                ],
              })
            }
          >
            Add column
          </Button>
        </div>
      </DataCard>

      <DataCard title="References">
        <div className="space-y-3">
          {value.references.map((reference, idx) => (
            <div
              key={`${reference.field}-${idx}`}
              className="grid grid-cols-1 gap-3 md:grid-cols-4 md:items-end"
            >
              <label className="text-sm text-gray-700">
                Field
                <SelectInput
                  value={reference.field}
                  onChange={(e) => {
                    const next = [...value.references];
                    next[idx] = { ...next[idx], field: e.target.value };
                    setValue({ ...value, references: next });
                  }}
                >
                  <option value="">Select field</option>
                  {value.fields.map((field) => (
                    <option key={field.name} value={field.name}>
                      {field.label || deriveDisplayLabel(field.name)} (
                      {field.name})
                    </option>
                  ))}
                </SelectInput>
              </label>
              <label className="text-sm text-gray-700">
                Target Resource Type
                <SelectInput
                  value={reference.target_type}
                  onChange={(e) => {
                    const next = [...value.references];
                    next[idx] = { ...next[idx], target_type: e.target.value };
                    setValue({ ...value, references: next });
                  }}
                >
                  <option value="">Select type</option>
                  {allTypes.map((typeDef) => (
                    <option key={typeDef.id} value={typeDef.id}>
                      {typeDef.title || typeDef.id}
                    </option>
                  ))}
                </SelectInput>
              </label>
              <label className="text-sm text-gray-700">
                Multiple
                <SelectInput
                  value={reference.multiple ? "yes" : "no"}
                  onChange={(e) => {
                    const next = [...value.references];
                    next[idx] = {
                      ...next[idx],
                      multiple: e.target.value === "yes",
                    };
                    setValue({ ...value, references: next });
                  }}
                >
                  <option value="no">No</option>
                  <option value="yes">Yes</option>
                </SelectInput>
              </label>
              <ActionButton
                tone="danger"
                onClick={() =>
                  setValue({
                    ...value,
                    references: value.references.filter((_, i) => i !== idx),
                  })
                }
              >
                Remove
              </ActionButton>
            </div>
          ))}
          <Button
            variant="secondary"
            onClick={() =>
              setValue({
                ...value,
                references: [
                  ...value.references,
                  { field: "", target_type: "", multiple: false },
                ],
              })
            }
          >
            Add reference
          </Button>
        </div>
      </DataCard>

      <DataCard title="Validation rules">
        <label className="block text-sm font-medium text-gray-700">
          validation_rules
          <TextareaInput
            rows={6}
            value={advanced.validation_rules}
            onChange={(e) =>
              setAdvanced({ ...advanced, validation_rules: e.target.value })
            }
          />
        </label>
      </DataCard>

      <DataCard title="Advanced JSON">
        <div className="space-y-4">
          <label className="block text-sm font-medium text-gray-700">
            form_layout
            <TextareaInput
              rows={6}
              value={advanced.form_layout}
              onChange={(e) =>
                setAdvanced({ ...advanced, form_layout: e.target.value })
              }
            />
          </label>
          <label className="block text-sm font-medium text-gray-700">
            detail_sections
            <TextareaInput
              rows={6}
              value={advanced.detail_sections}
              onChange={(e) =>
                setAdvanced({ ...advanced, detail_sections: e.target.value })
              }
            />
          </label>
        </div>
      </DataCard>

      <div className="flex justify-end">
        <Button onClick={save}>Save</Button>
      </div>
    </section>
  );
}
