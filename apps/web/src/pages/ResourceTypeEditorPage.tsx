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
import { useI18n } from "../i18n";
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
  const { t } = useI18n();
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

  const upsertReferenceForField = (
    fieldName: string,
    multiple: boolean,
    targetType?: string,
  ) => {
    if (!fieldName.trim()) return;
    setValue((prev) => {
      const index = prev.references.findIndex((ref) => ref.field === fieldName);
      if (index >= 0) {
        const next = [...prev.references];
        next[index] = {
          ...next[index],
          multiple,
          target_type: targetType ?? next[index].target_type,
        };
        return { ...prev, references: next };
      }
      return {
        ...prev,
        references: [
          ...prev.references,
          { field: fieldName, target_type: targetType ?? "", multiple },
        ],
      };
    });
  };

  const removeReferenceForField = (fieldName: string) => {
    setValue((prev) => ({
      ...prev,
      references: prev.references.filter((ref) => ref.field !== fieldName),
    }));
  };

  const removeField = (idx: number) => {
    const target = value.fields[idx]?.name;
    const fields = value.fields.filter((_, i) => i !== idx);
    const references = target
      ? value.references.filter((ref) => ref.field !== target)
      : value.references;
    const required_fields = target
      ? value.required_fields.filter((name) => name !== target)
      : value.required_fields;
    setValue({ ...value, fields, references, required_fields });
  };

  const setFieldName = (idx: number, name: string) => {
    setValue((prev) => {
      const current = prev.fields[idx];
      if (!current) return prev;
      const oldName = current.name;
      const autoLabel =
        current.label.trim() === "" ||
        current.label === deriveDisplayLabel(oldName);
      const nextLabel = autoLabel ? deriveDisplayLabel(name) : current.label;
      const fields = [...prev.fields];
      fields[idx] = { ...current, name, label: nextLabel };
      const references =
        oldName && oldName !== name
          ? prev.references.map((ref) =>
              ref.field === oldName ? { ...ref, field: name } : ref,
            )
          : prev.references;
      const required_fields =
        oldName && oldName !== name
          ? prev.required_fields.map((required) =>
              required === oldName ? name : required,
            )
          : prev.required_fields;
      return { ...prev, fields, references, required_fields };
    });
  };

  const setFieldType = (idx: number, type: FieldDef["type"]) => {
    const field = value.fields[idx];
    if (!field) return;
    upsertField(idx, { ...field, type });
    if (type === "reference") upsertReferenceForField(field.name, false);
    else if (type === "reference_array")
      upsertReferenceForField(field.name, true);
    else removeReferenceForField(field.name);
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
      const fields = value.fields
        .filter((field) => field.name.trim())
        .map((field) => ({
          ...field,
          enum_values:
            field.type === "enum"
              ? (field.enum_values ?? []).map((v) => v.trim()).filter(Boolean)
              : (field.enum_values ?? []),
        }));
      const fieldNames = new Set(fields.map((field) => field.name));
      const payload: ResourceType = {
        ...value,
        fields,
        required_fields: value.required_fields.filter(
          (name) => Boolean(name) && fieldNames.has(name),
        ),
        list_columns: normalizeListColumns(value.list_columns).map(
          (col) => col.path,
        ),
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
            ? `${t("Administration")} / ${t("Resource Types")} / ${t("Create Resource Type")}`
            : `${t("Administration")} / ${t("Resource Types")} / ${type} / Edit schema`
        }
      />
      <ErrorBanner message={error} />

      <DataCard title={t("General")}>
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
            {t("Title")}
            <TextInput
              value={value.title}
              onChange={(e) => setValue({ ...value, title: e.target.value })}
            />
          </label>
          <label className="block text-sm font-medium text-gray-700">
            {t("Group")}
            <TextInput
              value={value.group}
              onChange={(e) => setValue({ ...value, group: e.target.value })}
            />
          </label>
          <label className="block text-sm font-medium text-gray-700 md:col-span-2">
            {t("Description")}
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
        title={t("Fields")}
        actions={
          <div className="flex items-center gap-3">
            <Link
              to="/field-types"
              className="text-sm font-medium text-blue-700 hover:text-blue-800"
            >
              {t("Field Types guide")}
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
              {t("Add field")}
            </Button>
          </div>
        }
      >
        <div className="space-y-3">
          {value.fields.map((field, idx) => (
            <div key={idx} className="rounded-lg border border-gray-200 p-3">
              <div className="grid grid-cols-1 gap-3 md:grid-cols-5">
                <label className="text-sm text-gray-700">
                  {t("Field name")}
                  <TextInput
                    value={field.name}
                    onChange={(e) => setFieldName(idx, e.target.value)}
                  />
                </label>
                <label className="text-sm text-gray-700">
                  {t("Label")}
                  <TextInput
                    value={field.label}
                    onChange={(e) =>
                      upsertField(idx, { ...field, label: e.target.value })
                    }
                  />
                </label>
                <label className="text-sm text-gray-700">
                  {t("Type")}
                  <SelectInput
                    value={field.type}
                    onChange={(e) =>
                      setFieldType(idx, e.target.value as FieldDef["type"])
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
                  {t("Required")}
                  <SelectInput
                    value={
                      value.required_fields.includes(field.name) ? "yes" : "no"
                    }
                    onChange={(e) =>
                      toggleRequired(field.name, e.target.value === "yes")
                    }
                  >
                    <option value="no">{t("No")}</option>
                    <option value="yes">{t("Yes")}</option>
                  </SelectInput>
                </label>
                <div className="flex items-end">
                  <ActionButton tone="danger" onClick={() => removeField(idx)}>
                    {t("Remove")}
                  </ActionButton>
                </div>
              </div>

              {field.type === "enum" ? (
                <div className="mt-3">
                  <p className="text-sm font-medium text-gray-700">
                    {t("Enum values")}
                  </p>
                  <div className="mt-2 space-y-2">
                    {(field.enum_values ?? []).map((valueText, enumIndex) => (
                      <div key={enumIndex} className="flex items-center gap-2">
                        <TextInput
                          value={valueText}
                          onChange={(e) => {
                            const enumValues = [...field.enum_values];
                            enumValues[enumIndex] = e.target.value;
                            upsertField(idx, {
                              ...field,
                              enum_values: enumValues,
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
                          {t("Remove")}
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
                      {t("Add value")}
                    </Button>
                  </div>
                </div>
              ) : null}

              {field.type === "reference" ||
              field.type === "reference_array" ? (
                <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                  <label className="text-sm text-gray-700">
                    {t("Target Resource Type")}
                    <SelectInput
                      value={
                        value.references.find((ref) => ref.field === field.name)
                          ?.target_type ?? ""
                      }
                      onChange={(e) =>
                        upsertReferenceForField(
                          field.name,
                          field.type === "reference_array",
                          e.target.value,
                        )
                      }
                    >
                      <option value="">{t("Select type")}</option>
                      {allTypes.map((typeDef) => (
                        <option key={typeDef.id} value={typeDef.id}>
                          {typeDef.title || typeDef.id}
                        </option>
                      ))}
                    </SelectInput>
                  </label>
                  <div className="text-xs text-gray-500 md:self-end md:pb-2">
                    {field.type === "reference_array"
                      ? t(
                          "Multiple is derived from field type: reference_array => yes.",
                        )
                      : t(
                          "Multiple is derived from field type: reference => no.",
                        )}
                  </div>
                </div>
              ) : null}
            </div>
          ))}
        </div>
      </DataCard>

      <DataCard title={t("List columns")}>
        <div className="space-y-3">
          {normalizeListColumns(value.list_columns).map((column, idx) => (
            <div
              key={idx}
              className="grid grid-cols-1 gap-3 md:grid-cols-[1fr_1fr_auto] md:items-end"
            >
              <label className="text-sm text-gray-700">
                {t("Path")}
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
                {t("Label")}
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
                {t("Remove")}
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
                  { path: "metadata.name", label: t("Name") },
                ],
              })
            }
          >
            {t("Add column")}
          </Button>
        </div>
      </DataCard>

      <DataCard title={t("References")}>
        {value.references.length === 0 ? (
          <p className="text-sm text-gray-600">
            {t("No references configured.")}
          </p>
        ) : (
          <ul className="space-y-2 text-sm text-gray-700">
            {value.references.map((reference) => (
              <li key={`${reference.field}-${reference.target_type}`}>
                {reference.field} -&gt; {reference.target_type}
                {reference.multiple ? "[]" : ""}
              </li>
            ))}
          </ul>
        )}
      </DataCard>

      <DataCard title={t("Validation rules")}>
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

      <DataCard title={t("Advanced JSON")}>
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
        <Button onClick={save}>{t("Save")}</Button>
      </div>
    </section>
  );
}
