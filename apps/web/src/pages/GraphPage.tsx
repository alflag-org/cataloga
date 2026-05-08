import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { api } from "../api/client";
import { ErrorBanner } from "../components/ErrorBanner";
import { PageHeader } from "../components/PageHeader";
import { ResourceGraph } from "../components/ResourceGraph";
import { useI18n } from "../i18n";
import type { Resource, ResourceType } from "../types";

export function GraphPage() {
  const { t } = useI18n();
  const [types, setTypes] = useState<ResourceType[]>([]);
  const [resourceByType, setResourceByType] = useState<
    Record<string, Resource[]>
  >({});
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    (async () => {
      try {
        const rt = await api.listResourceTypes();
        setTypes(rt);
        const resources = await Promise.all(
          rt.map(
            async (type) =>
              [type.id, await api.listResources(type.id)] as const,
          ),
        );
        setResourceByType(Object.fromEntries(resources));
      } catch (e) {
        setError(e instanceof Error ? e.message : String(e));
      }
    })();
  }, []);

  return (
    <section className="space-y-5">
      <PageHeader
        title={t("Graph")}
        subtitle={t("Explore Resource relationships in a larger workspace.")}
        actions={
          <Link
            to="/"
            className="text-sm font-medium text-blue-700 hover:text-blue-800"
          >
            {t("Back to dashboard")}
          </Link>
        }
      />
      <ErrorBanner message={error} />
      <ResourceGraph expanded types={types} resourcesByType={resourceByType} />
    </section>
  );
}
