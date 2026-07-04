import { useEffect, useState } from "react";
import { api } from "../api/client";
import type { Resource, ResourceType } from "../types";
import { Button } from "./Button";
import { DataCard } from "./DataCard";
import { ErrorBanner } from "./ErrorBanner";
import { FieldInput } from "./FieldInput";
import type { ReferenceOption } from "./ReferencePicker";
import { TextInput } from "./TextInput";
import { TextareaInput } from "./TextareaInput";
import { useI18n } from "../i18n";
import { buildResourcePayload } from "../resourcePayload";

type Props = {
  resourceType: ResourceType;
  allTypes: ResourceType[];
  initial: Resource;
  mode: "create" | "edit";
  onSubmit: (resource: Resource) => Promise<void>;
};

function getReferenceForField(resourceType: ResourceType, fieldName: string) {
  return resourceType.references.find((r) => r.field === fieldName);
}

export function ResourceForm({
  resourceType,
  allTypes,
  initial,
  mode,
  onSubmit,
}: Props) {
  const { t } = useI18n();
  const [form, setForm] = useState<Resource>(initial);
  const [customFieldsText, setCustomFieldsText] = useState(
    JSON.stringify(initial.custom_fields ?? {}, null, 2),
  );
  const [dependenciesText, setDependenciesText] = useState(
    JSON.stringify(initial.dependencies ?? {}, null, 2),
  );
  const [error, setError] = useState<string | null>(null);
  const [referenceOptions, setReferenceOptions] = useState<
    Record<string, ReferenceOption[]>
  >({});
  const [referenceLoading, setReferenceLoading] = useState<
    Record<string, boolean>
  >({});
  const [referenceErrors, setReferenceErrors] = useState<
    Record<string, string>
  >({});
  useEffect(() => {
    let alive = true;
    (async () => {
      const targetTypes = Array.from(
        new Set(
          resourceType.references
            .map((reference) => reference.target_type)
            .filter(Boolean),
        ),
      );
      const typeById = new Map(
        allTypes.map((typeDef) => [typeDef.id, typeDef]),
      );

      if (alive) {
        setReferenceLoading((prev) => ({
          ...prev,
          ...Object.fromEntries(
            targetTypes.map((targetType) => [targetType, true]),
          ),
        }));
      }

      const results = await Promise.all(
        targetTypes.map(async (targetType) => {
          try {
            const items = await api.listResources(targetType);
            const options: ReferenceOption[] = items.map((resource) => ({
              id: resource.id,
              name: resource.name || resource.id,
              typeId: targetType,
              typeTitle: typeById.get(targetType)?.title || targetType,
              description:
                typeof resource.spec.description === "string"
                  ? resource.spec.description
                  : undefined,
            }));
            return { targetType, options, error: null } as const;
          } catch (e) {
            return {
              targetType,
              options: [] as ReferenceOption[],
              error: e instanceof Error ? e.message : String(e),
            } as const;
          }
        }),
      );

      if (!alive) return;

      setReferenceOptions((prev) => {
        const next = { ...prev };
        for (const result of results) {
          next[result.targetType] = result.options;
        }
        return next;
      });
      setReferenceErrors((prev) => {
        const next = { ...prev };
        for (const result of results) {
          if (result.error) next[result.targetType] = result.error;
          else delete next[result.targetType];
        }
        return next;
      });
      setReferenceLoading((prev) => {
        const next = { ...prev };
        for (const result of results) {
          next[result.targetType] = false;
        }
        return next;
      });
    })();

    return () => {
      alive = false;
    };
  }, [allTypes, resourceType.references]);

  const submit = async () => {
    try {
      setError(null);
      const next = buildResourcePayload(
        resourceType,
        form,
        customFieldsText,
        dependenciesText,
      );
      await onSubmit(next);
    } catch (e) {
      setError(e instanceof Error ? e.message : String(e));
    }
  };

  return (
    <div className="space-y-5">
      <ErrorBanner message={error} />
      <DataCard title={t("Resource")}>
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <label className="block text-sm font-medium text-gray-700">
            {t("ID")}
            <TextInput
              value={form.id}
              autoFocus={mode === "create"}
              onChange={(e) =>
                setForm({
                  ...form,
                  id: e.target.value,
                })
              }
            />
          </label>
          <label className="block text-sm font-medium text-gray-700">
            {t("Name")}
            <TextInput
              value={form.name}
              onChange={(e) =>
                setForm({
                  ...form,
                  name: e.target.value,
                })
              }
            />
          </label>
        </div>
      </DataCard>
      <DataCard title={t("Fields")}>
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
                const targetTypeTitle = reference
                  ? allTypes.find(
                      (typeDef) => typeDef.id === reference.target_type,
                    )?.title || reference.target_type
                  : undefined;
                return (
                  <FieldInput
                    field={field}
                    value={form.spec[field.name]}
                    reference={
                      reference
                        ? {
                            multiple: reference.multiple,
                            targetType: reference.target_type,
                            targetTypeTitle,
                            options,
                            loading:
                              referenceLoading[reference.target_type] ?? false,
                            error:
                              referenceErrors[reference.target_type] ?? null,
                          }
                        : undefined
                    }
                    onChange={(nextValue) =>
                      setForm({
                        ...form,
                        spec: {
                          ...form.spec,
                          [field.name]: nextValue,
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
      <DataCard title={t("Advanced")}>
        <div className="grid grid-cols-1 gap-4">
          <label className="block text-sm font-medium text-gray-700">
            {t("custom_fields JSON")}
            <TextareaInput
              rows={5}
              value={customFieldsText}
              onChange={(e) => setCustomFieldsText(e.target.value)}
            />
          </label>
          <label className="block text-sm font-medium text-gray-700">
            {t("dependencies JSON")}
            <TextareaInput
              rows={5}
              value={dependenciesText}
              onChange={(e) => setDependenciesText(e.target.value)}
            />
          </label>
        </div>
      </DataCard>
      <div className="flex justify-end">
        <Button onClick={submit}>{t("Save")}</Button>
      </div>
    </div>
  );
}
