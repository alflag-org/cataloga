import { useEffect, useState } from "react";
import { api } from "../api/client";
import { ActionLink } from "../components/Action";
import { DataCard } from "../components/DataCard";
import { ErrorBanner } from "../components/ErrorBanner";
import { PageHeader } from "../components/PageHeader";
import { useI18n } from "../i18n";
import type { ValidationResult } from "../types";

export function ValidationPage() {
  const { t } = useI18n();
  const [result, setResult] = useState<ValidationResult | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api
      .getValidation()
      .then(setResult)
      .catch((e) => setError(e.message));
  }, []);

  return (
    <section className="space-y-5">
      <PageHeader title={t("Validation")} />
      <ErrorBanner message={error} />
      {result ? (
        <DataCard>
          <div className="space-y-3 text-sm">
            <p>
              {t("Status")}: {result.status === "ok" ? t("OK") : t("Failed")}
            </p>
            <p>
              {t("Errors")}: {result.errors.length}
            </p>
            <p>
              {t("Warnings")}: {result.warnings.length}
            </p>
            {result.errors.map((item, idx) => (
              <div
                key={idx}
                className="rounded border border-red-200 bg-red-50 px-3 py-2 text-red-900"
              >
                <p>
                  {t("Resource")}:{" "}
                  {item.resource_type && item.resource_id
                    ? `${item.resource_type} / ${item.resource_id}`
                    : "-"}
                </p>
                <p>
                  {t("Field")}: {item.field || "-"}
                </p>
                <p>
                  {t("Message")}: {item.message}
                </p>
                {item.resource_type && item.resource_id ? (
                  <ActionLink
                    tone="primary"
                    className="text-xs underline underline-offset-2"
                    to={`/resources/${item.resource_type}/${item.resource_id}`}
                  >
                    {t("Show")}
                  </ActionLink>
                ) : null}
              </div>
            ))}
          </div>
        </DataCard>
      ) : null}
    </section>
  );
}
