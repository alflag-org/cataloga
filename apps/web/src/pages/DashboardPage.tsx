import { useEffect, useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { api } from "../api/client";
import { DataCard } from "../components/DataCard";
import { DataTable, type DataTableColumn } from "../components/DataTable";
import { ErrorBanner } from "../components/ErrorBanner";
import { PageHeader } from "../components/PageHeader";
import { ResourceGraph } from "../components/ResourceGraph";
import { useI18n } from "../i18n";
import type { Resource, ResourceType } from "../types";

type SearchHit = { type: string; typeTitle: string; id: string; name: string };
type ResourceRow = { group: string; title: string; id: string; count: number };

export function DashboardPage() {
  const { t } = useI18n();
  const [types, setTypes] = useState<ResourceType[]>([]);
  const [resourceByType, setResourceByType] = useState<
    Record<string, Resource[]>
  >({});
  const [query, setQuery] = useState("");
  const [resourceTableQuery, setResourceTableQuery] = useState("");
  const [sortKey, setSortKey] = useState("group");
  const [sortDir, setSortDir] = useState<"asc" | "desc">("asc");
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
      } catch (e) {
        setError(e instanceof Error ? e.message : String(e));
      }
    })();
  }, []);

  const searchHits = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return [];
    const hits: SearchHit[] = [];
    for (const t of types) {
      for (const r of resourceByType[t.id] ?? []) {
        const haystack = [
          r.id,
          r.name,
          JSON.stringify(r.tags),
          JSON.stringify(r.spec),
        ]
          .join(" ")
          .toLowerCase();
        if (haystack.includes(q)) {
          hits.push({
            type: t.id,
            typeTitle: t.title || t.id,
            id: r.id,
            name: r.name || r.id,
          });
        }
      }
    }
    return hits.slice(0, 30);
  }, [query, resourceByType, types]);

  const rows = useMemo<ResourceRow[]>(() => {
    return types
      .map((type) => ({
        group: type.group?.trim() || t("Other"),
        title: type.title || type.id,
        id: type.id,
        count: (resourceByType[type.id] ?? []).length,
      }))
      .sort((a, b) => {
        const g = a.group.localeCompare(b.group);
        if (g !== 0) return g;
        return a.title.localeCompare(b.title);
      });
  }, [resourceByType, types]);

  const filteredRows = rows.filter((row) => {
    const q = resourceTableQuery.trim().toLowerCase();
    if (!q) return true;
    return [row.group, row.title, row.id].join(" ").toLowerCase().includes(q);
  });

  const columns: DataTableColumn<ResourceRow>[] = [
    {
      key: "group",
      label: t("Group"),
      render: (row) => row.group,
      sortValue: (row) => row.group,
    },
    {
      key: "type",
      label: t("Type"),
      render: (row) => row.title,
      sortValue: (row) => row.title,
    },
    {
      key: "resources",
      label: t("Resources"),
      render: (row) => row.count,
      sortValue: (row) => row.count,
    },
  ];

  return (
    <section className="space-y-5">
      <PageHeader title={t("Dashboard")} />
      <ErrorBanner message={error} />

      <DataCard
        title={t("Graph")}
        actions={
          <Link
            to="/graph"
            className="text-sm font-medium text-blue-700 hover:text-blue-800"
          >
            {t("Open graph")}
          </Link>
        }
      >
        <ResourceGraph compact types={types} resourcesByType={resourceByType} />
      </DataCard>

      <DataCard title={t("Resource search")}>
        <div className="space-y-2">
          <label className="text-sm font-medium text-gray-700">
            {t("Search resources")}
            <input
              className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
            />
          </label>
          {query.trim() ? (
            <div className="space-y-1 text-sm">
              {searchHits.length === 0 ? (
                <p className="text-gray-600">{t("No results")}</p>
              ) : (
                searchHits.map((hit) => (
                  <Link
                    key={`${hit.type}/${hit.id}`}
                    to={`/resources/${hit.type}/${hit.id}`}
                    className="block text-blue-700 hover:text-blue-800"
                  >
                    {hit.typeTitle} / {hit.name}
                  </Link>
                ))
              )}
            </div>
          ) : null}
        </div>
      </DataCard>

      <DataCard
        title={t("Resources")}
        actions={
          <Link
            to="/resources"
            className="text-sm font-medium text-blue-700 hover:text-blue-800"
          >
            {t("View all resources")}
          </Link>
        }
      >
        <DataTable
          columns={columns}
          rows={filteredRows.slice(0, 8)}
          searchValue={resourceTableQuery}
          onSearchChange={setResourceTableQuery}
          sortKey={sortKey}
          sortDir={sortDir}
          onSort={(key) => {
            if (sortKey === key)
              setSortDir((prev) => (prev === "asc" ? "desc" : "asc"));
            else {
              setSortKey(key);
              setSortDir("asc");
            }
          }}
          rowKey={(row) => row.id}
          empty={
            <p className="text-sm text-gray-600">{t("No Resource Types")}</p>
          }
        />
      </DataCard>
    </section>
  );
}
