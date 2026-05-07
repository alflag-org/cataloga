import { useState } from "react";
import { api } from "../api/client";
import { Button } from "../components/Button";
import { DataCard } from "../components/DataCard";
import { ErrorBanner } from "../components/ErrorBanner";
import { PageHeader } from "../components/PageHeader";
import { useI18n } from "../i18n";

export function ExportPage() {
  const { t } = useI18n();
  const [yaml, setYaml] = useState("");
  const [error, setError] = useState<string | null>(null);

  return (
    <section className="space-y-5">
      <PageHeader title={t("Administration / Export")} />
      <ErrorBanner message={error} />
      <DataCard>
        <div className="space-y-4">
          <Button
            onClick={() =>
              api
                .exportYaml()
                .then(setYaml)
                .catch((e) => setError(e.message))
            }
          >
            {t("Export YAML")}
          </Button>
          <pre className="max-h-[60vh] overflow-auto rounded-lg bg-gray-950 p-4 text-xs text-gray-100">
            {yaml}
          </pre>
        </div>
      </DataCard>
    </section>
  );
}
