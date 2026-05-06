import { useEffect, useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { api } from "../api/client";
import { DataCard } from "../components/DataCard";
import { DataTable, FilterSelect, type DataTableColumn } from "../components/DataTable";
import { ErrorBanner } from "../components/ErrorBanner";
import { PageHeader } from "../components/PageHeader";
import type { ResourceType } from "../types";

type Row = {
  group: string;
  typeTitle: string;
  typeId: string;
  count: number;
};

export function ResourcesIndexPage() {
  const [types, setTypes] = useState<ResourceType[]>([]);
  const [counts, setCounts] = useState<Record<string, number>>({});
  const [query, setQuery] = useState("");
  const [groupFilter, setGroupFilter] = useState("all");
  const [hasResourcesFilter, setHasResourcesFilter] = useState("all");
  const [sortKey, setSortKey] = useState("group");
  const [sortDir, setSortDir] = useState<"asc" | "desc">("asc");
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    (async () => {
      try {
        const rt = await api.listResourceTypes();
        setTypes(rt);
        const entries = await Promise.all(rt.map(async (t) => [t.id, (await api.listResources(t.id)).length] as const));
        setCounts(Object.fromEntries(entries));
      } catch (e) {
        setError(e instanceof Error ? e.message : String(e));
      }
    })();
  }, []);

  const rows = useMemo<Row[]>(() => {
    return types.map((type) => ({
      group: type.group?.trim() || "Other",
      typeTitle: type.title || type.id,
      typeId: type.id,
      count: counts[type.id] ?? 0,
    }));
  }, [counts, types]);

  const groups = useMemo(() => {
    const set = new Set(rows.map((row) => row.group));
    return ["all", ...[...set].sort((a, b) => a.localeCompare(b))];
  }, [rows]);

  const filteredRows = rows.filter((row) => {
    const q = query.trim().toLowerCase();
    if (q && ![row.group, row.typeTitle, row.typeId].join(" ").toLowerCase().includes(q)) return false;
    if (groupFilter !== "all" && row.group !== groupFilter) return false;
    if (hasResourcesFilter === "has" && row.count === 0) return false;
    if (hasResourcesFilter === "empty" && row.count > 0) return false;
    return true;
  });

  const columns: DataTableColumn<Row>[] = [
    { key: "group", label: "Group", render: (row) => row.group, sortValue: (row) => row.group },
    { key: "type", label: "Type", render: (row) => row.typeTitle, sortValue: (row) => row.typeTitle },
    { key: "id", label: "ID", render: (row) => row.typeId, sortValue: (row) => row.typeId },
    { key: "resources", label: "Resources", render: (row) => row.count, sortValue: (row) => row.count },
    {
      key: "actions",
      label: "Actions",
      render: (row) => (
        <Link className="text-blue-700 hover:text-blue-800" to={`/resources/${row.typeId}`}>
          View resources
        </Link>
      ),
      sortValue: () => "",
    },
  ];

  return (
    <section className="space-y-5">
      <PageHeader title="Resources" />
      <ErrorBanner message={error} />
      <DataCard>
        {rows.length === 0 ? (
          <div className="text-sm text-gray-700">
            <p className="font-medium text-gray-900">No Resource Types</p>
            <p className="mt-1">Create Resource Types from Administration / Resource Types.</p>
          </div>
        ) : (
          <DataTable
            columns={columns}
            rows={filteredRows}
            searchValue={query}
            onSearchChange={setQuery}
            sortKey={sortKey}
            sortDir={sortDir}
            onSort={(key) => {
              if (sortKey === key) setSortDir((prev) => (prev === "asc" ? "desc" : "asc"));
              else {
                setSortKey(key);
                setSortDir("asc");
              }
            }}
            rowKey={(row) => row.typeId}
            filters={
              <>
                <FilterSelect
                  label="Group"
                  value={groupFilter}
                  onChange={setGroupFilter}
                  options={groups.map((group) => ({ value: group, label: group === "all" ? "All" : group }))}
                />
                <FilterSelect
                  label="Has resources"
                  value={hasResourcesFilter}
                  onChange={setHasResourcesFilter}
                  options={[
                    { value: "all", label: "All" },
                    { value: "has", label: "Has resources" },
                    { value: "empty", label: "Empty" },
                  ]}
                />
              </>
            }
          />
        )}
      </DataCard>
    </section>
  );
}
