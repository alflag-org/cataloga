import { useState } from "react";
import { api } from "../api/client";
import { Button } from "../components/Button";
import { DataCard } from "../components/DataCard";
import { ErrorBanner } from "../components/ErrorBanner";
import { PageHeader } from "../components/PageHeader";
import { TextareaInput } from "../components/TextareaInput";
import { useI18n } from "../i18n";
import type { ImportPreviewResult } from "../types";

export function ImportPage() {
  const { t } = useI18n();
  const [yaml, setYaml] = useState("");
  const [preview, setPreview] = useState<ImportPreviewResult | null>(null);
  const [error, setError] = useState<string | null>(null);

  return (
    <section className="space-y-5">
      <PageHeader title={t("Administration / Import")} />
      <ErrorBanner message={error} />
      <DataCard title="YAML">
        <div className="space-y-4">
          <TextareaInput
            rows={20}
            value={yaml}
            onChange={(e) => {
              setYaml(e.target.value);
              setPreview(null);
            }}
          />
          <Button
            variant="secondary"
            onClick={async () => {
              try {
                setError(null);
                setPreview(await api.importPreview(yaml));
              } catch (e) {
                setError(e instanceof Error ? e.message : String(e));
              }
            }}
          >
            {t("Preview import")}
          </Button>
        </div>
      </DataCard>
      {preview ? (
        <DataCard title={t("Preview")}>
          <div className="space-y-2 text-sm">
            <p>
              {t("Resource Types to create")}:{" "}
              {preview.resource_types_to_create.length}
            </p>
            <p>
              {t("Resource Types to update")}:{" "}
              {preview.resource_types_to_update.length}
            </p>
            <p>
              {t("Resources to create")}: {preview.resources_to_create.length}
            </p>
            <p>
              {t("Resources to update")}: {preview.resources_to_update.length}
            </p>
            <p>
              {t("Validation errors")}: {preview.validation_errors.length}
            </p>
            {preview.validation_errors.length > 0 ? (
              <div className="space-y-2 pt-2">
                {preview.validation_errors.map((item, idx) => (
                  <div
                    key={idx}
                    className="rounded border border-red-200 bg-red-50 px-3 py-2 text-red-900"
                  >
                    <div className="grid grid-cols-1 gap-2 md:grid-cols-3">
                      <p>
                        <span className="font-semibold">
                          {t("Resource Type")}:
                        </span>{" "}
                        {item.resource_type || "-"}
                      </p>
                      <p>
                        <span className="font-semibold">{t("Resource")}:</span>{" "}
                        {item.resource_id || "-"}
                      </p>
                      <p>
                        <span className="font-semibold">{t("Field")}:</span>{" "}
                        {item.field || "-"}
                      </p>
                    </div>
                    <p className="mt-2 break-words">
                      <span className="font-semibold">{t("Message")}:</span>{" "}
                      {item.message}
                    </p>
                  </div>
                ))}
              </div>
            ) : null}
            <Button
              onClick={async () => {
                try {
                  setError(null);
                  await api.importApply(yaml);
                } catch (e) {
                  setError(e instanceof Error ? e.message : String(e));
                }
              }}
              disabled={preview.validation_errors.length > 0}
            >
              {t("Apply import")}
            </Button>
          </div>
        </DataCard>
      ) : null}
    </section>
  );
}
