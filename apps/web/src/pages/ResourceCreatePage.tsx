import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { api } from "../api/client";
import { ErrorBanner } from "../components/ErrorBanner";
import { PageHeader } from "../components/PageHeader";
import { ResourceForm } from "../components/ResourceForm";
import { defaultResource, type ResourceType } from "../types";

export function ResourceCreatePage() {
  const { type = "" } = useParams();
  const navigate = useNavigate();
  const [rt, setRt] = useState<ResourceType | null>(null);
  const [allTypes, setAllTypes] = useState<ResourceType[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    (async () => {
      try {
        const [resourceType, resourceTypes] = await Promise.all([
          api.getResourceType(type),
          api.listResourceTypes(),
        ]);
        setRt(resourceType);
        setAllTypes(resourceTypes);
      } catch (e) {
        setError(e instanceof Error ? e.message : String(e));
      }
    })();
  }, [type]);

  if (!rt) return <ErrorBanner message={error || "loading"} />;

  return (
    <section className="space-y-5">
      <PageHeader title={`Resources / ${rt.title || type} / Create`} />
      <ResourceForm
        resourceType={rt}
        allTypes={allTypes}
        initial={defaultResource(type)}
        mode="create"
        onSubmit={async (resource) => {
          await api.createResource(type, resource);
          navigate(`/resources/${type}`);
        }}
      />
    </section>
  );
}
