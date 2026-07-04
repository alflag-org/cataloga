import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { api } from "../api/client";
import { ErrorBanner } from "../components/ErrorBanner";
import { PageHeader } from "../components/PageHeader";
import { ResourceForm } from "../components/ResourceForm";
import { useI18n } from "../i18n";
import type { Resource, ResourceType } from "../types";

export function ResourceEditPage() {
  const { t } = useI18n();
  const { type = "", id = "" } = useParams();
  const navigate = useNavigate();
  const [rt, setRt] = useState<ResourceType | null>(null);
  const [allTypes, setAllTypes] = useState<ResourceType[]>([]);
  const [resource, setResource] = useState<Resource | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    (async () => {
      try {
        const [resourceType, current, resourceTypes] = await Promise.all([
          api.getResourceType(type),
          api.getResource(type, id),
          api.listResourceTypes(),
        ]);
        setRt(resourceType);
        setResource(current);
        setAllTypes(resourceTypes);
      } catch (e) {
        setError(e instanceof Error ? e.message : String(e));
      }
    })();
  }, [type, id]);

  if (!rt || !resource) return <ErrorBanner message={error || t("loading")} />;

  return (
    <section className="space-y-5">
      <PageHeader
        title={`${t("Resources")} / ${rt.title || type} / ${id} / ${t("Edit")}`}
      />
      <ResourceForm
        resourceType={rt}
        allTypes={allTypes}
        initial={resource}
        mode="edit"
        onSubmit={async (next) => {
          await api.updateResource(type, id, next);
          navigate(`/resources/${next.type}/${next.id}`);
        }}
      />
    </section>
  );
}
