import { useEffect, useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { api } from "../api/client";
import { DataCard } from "../components/DataCard";
import { DataTable, type DataTableColumn } from "../components/DataTable";
import { ErrorBanner } from "../components/ErrorBanner";
import { PageHeader } from "../components/PageHeader";
import { ResourceGraph } from "../components/ResourceGraph";
import type { Resource, ResourceType } from "../types";

type SearchHit = { type: string; typeTitle: string; id: string; name: string };
type ResourceRow = { group: string; title: string; id: string; count: number };

export function DashboardPage() {
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
          r.metadata.id,
          r.metadata.name,
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
    return hits.slice(0, 30);
  }, [query, resourceByType, types]);

  const rows = useMemo<ResourceRow[]>(() => {
    return types
      .map((type) => ({
        group: type.group?.trim() || "Other",
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
      label: "Group",
      render: (row) => row.group,
      sortValue: (row) => row.group,
    },
    {
      key: "type",
      label: "Type",
      render: (row) => row.title,
      sortValue: (row) => row.title,
    },
    {
      key: "resources",
      label: "Resources",
      render: (row) => row.count,
      sortValue: (row) => row.count,
    },
  ];

  return (
    <section className="space-y-5">
      <PageHeader title="Dashboard" />
      <ErrorBanner message={error} />

      <DataCard title="Graph">
        <ResourceGraph compact types={types} resourcesByType={resourceByType} />
      </DataCard>

      <DataCard title="Resource search">
        <div className="space-y-2">
          <label className="text-sm font-medium text-gray-700">
            Search resources
            <input
              className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
            />
          </label>
          {query.trim() ? (
            <div className="space-y-1 text-sm">
              {searchHits.length === 0 ? (
                <p className="text-gray-600">No results</p>
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
        title="Resources"
        actions={
          <Link
            to="/resources"
            className="text-sm font-medium text-blue-700 hover:text-blue-800"
          >
            View all resources
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
          empty={<p className="text-sm text-gray-600">No Resource Types</p>}
        />
      </DataCard>
    </section>
  );
}
