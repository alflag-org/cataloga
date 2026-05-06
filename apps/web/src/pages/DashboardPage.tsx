import { useEffect, useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { api } from "../api/client";
import { DataCard } from "../components/DataCard";
import { ErrorBanner } from "../components/ErrorBanner";
import { PageHeader } from "../components/PageHeader";
import { ResourceTypeCard } from "../components/ResourceTypeCard";
import { StatCard } from "../components/StatCard";
import { TextInput } from "../components/TextInput";
import type { Resource, ResourceType } from "../types";

type SearchHit = { type: string; typeTitle: string; id: string; name: string };

export function DashboardPage() {
  const [types, setTypes] = useState<ResourceType[]>([]);
  const [resourceByType, setResourceByType] = useState<
    Record<string, Resource[]>
  >({});
  const [health, setHealth] = useState("unknown");
  const [validationStatus, setValidationStatus] = useState<
    "ok" | "failed" | "unknown"
  >("unknown");
  const [validationErrors, setValidationErrors] = useState(0);
  const [validationWarnings, setValidationWarnings] = useState(0);
  const [query, setQuery] = useState("");
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    (async () => {
      try {
        const rt = await api.listResourceTypes();
        setTypes(rt);
        const resources = await Promise.all(
          rt.map(async (t) => [t.id, await api.listResources(t.id)] as const),
        );
        setResourceByType(Object.fromEntries(resources));
        const h = await api.health();
        setHealth(h.status);
        const validation = await api.getValidation();
        setValidationStatus(validation.status);
        setValidationErrors(validation.errors.length);
        setValidationWarnings(validation.warnings.length);
      } catch (e) {
        setError(e instanceof Error ? e.message : String(e));
      }
    })();
  }, []);

  const counts = useMemo(
    () =>
      Object.fromEntries(
        Object.entries(resourceByType).map(([k, v]) => [k, v.length]),
      ),
    [resourceByType],
  );
  const totalResources = useMemo(
    () => Object.values(counts).reduce((acc, x) => acc + x, 0),
    [counts],
  );

  const grouped = useMemo(() => {
    return types.reduce<Record<string, ResourceType[]>>((acc, t) => {
      const g = t.group?.trim() || "Other";
      acc[g] = acc[g] ?? [];
      acc[g].push(t);
      return acc;
    }, {});
  }, [types]);

  const searchHits = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return [];
    const hits: SearchHit[] = [];
    for (const t of types) {
      const rows = resourceByType[t.id] ?? [];
      for (const r of rows) {
        const haystack = [
          r.metadata.id,
          r.metadata.name,
          r.metadata.type,
          JSON.stringify(r.metadata.tags),
          JSON.stringify(r.spec),
        ]
          .join(" ")
          .toLowerCase();
        if (haystack.includes(q)) {
          hits.push({
            type: t.id,
            typeTitle: t.title || t.id,
            id: r.metadata.id,
            name: r.metadata.name || r.metadata.id,
          });
        }
      }
    }
    return hits.slice(0, 100);
  }, [query, types, resourceByType]);

  return (
    <section className="space-y-5">
      <PageHeader title="Dashboard" />
      <ErrorBanner message={error} />

      <DataCard>
        <div className="space-y-2">
          <p className="text-sm font-medium text-gray-700">Search resources</p>
          <TextInput value={query} onChange={(e) => setQuery(e.target.value)} />
          {query.trim() ? (
            <div className="space-y-1 pt-2 text-sm">
              {searchHits.length ? (
                searchHits.map((hit) => (
                  <Link
                    key={`${hit.type}/${hit.id}`}
                    to={`/resources/${hit.type}/${hit.id}`}
                    className="block text-blue-700 hover:text-blue-800"
                  >
                    {hit.typeTitle} / {hit.name}
                  </Link>
                ))
              ) : (
                <p className="text-gray-600">No results</p>
              )}
            </div>
          ) : null}
        </div>
      </DataCard>

      <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
        <StatCard label="Resources" value={totalResources} />
        <StatCard label="Types" value={types.length} />
        <StatCard label="Health" value={health} />
      </div>

      <DataCard title="Resources">
        <div className="space-y-5">
          {Object.entries(grouped).map(([group, groupTypes]) => (
            <div key={group} className="space-y-3">
              <h3 className="text-sm font-semibold uppercase tracking-wide text-gray-700">
                {group}
              </h3>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {groupTypes.map((t) => (
                  <ResourceTypeCard
                    key={t.id}
                    title={t.title || t.id}
                    typeId={t.id}
                    count={counts[t.id] ?? 0}
                    to={`/resources/${t.id}`}
                  />
                ))}
              </div>
            </div>
          ))}
        </div>
      </DataCard>

      <DataCard title="Basic validation">
        <p className="text-sm text-gray-700">
          {validationStatus === "failed"
            ? "Failed"
            : validationStatus === "ok"
              ? "OK"
              : "Unknown"}
        </p>
        {validationStatus === "failed" ||
        validationWarnings > 0 ||
        validationErrors > 0 ? (
          <div className="pt-2 text-sm text-gray-700">
            <p>Errors: {validationErrors}</p>
            <p>Warnings: {validationWarnings}</p>
            <Link
              to="/validation"
              className="mt-2 inline-block text-blue-700 hover:text-blue-800"
            >
              View validation details
            </Link>
          </div>
        ) : null}
      </DataCard>
    </section>
  );
}
