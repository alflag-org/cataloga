import { useEffect, useState } from "react";
import { api } from "../api/client";
import { LinkButton } from "../components/Button";
import { ActionButton, ActionLink } from "../components/Action";
import { DataCard } from "../components/DataCard";
import { ErrorBanner } from "../components/ErrorBanner";
import { PageHeader } from "../components/PageHeader";
import { TextInput } from "../components/TextInput";
import type { ResourceType } from "../types";

export function ResourceTypeListPage() {
  const [items, setItems] = useState<ResourceType[]>([]);
  const [counts, setCounts] = useState<Record<string, number>>({});
  const [query, setQuery] = useState("");
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    (async () => {
      try {
        const rt = await api.listResourceTypes();
        setItems(rt);
        const entries = await Promise.all(
          rt.map(
            async (t) =>
              [t.id, (await api.listResources(t.id)).length] as const,
          ),
        );
        setCounts(Object.fromEntries(entries));
      } catch (e) {
        setError(e instanceof Error ? e.message : String(e));
      }
    })();
  }, []);

  const filtered = items.filter((t) => {
    const q = query.trim().toLowerCase();
    if (!q) return true;
    return [t.id, t.title, t.group].join(" ").toLowerCase().includes(q);
  });

  return (
    <section className="space-y-5">
      <PageHeader
        title="Administration / Resource Types"
        actions={
          <LinkButton to="/resource-types/new">Create Resource Type</LinkButton>
        }
      />
      <ErrorBanner message={error} />
      <DataCard>
        <div className="mb-4">
          <TextInput
            placeholder="Search"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
          />
        </div>
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200 text-sm">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-3 py-2 text-left font-semibold text-gray-600">
                  Title
                </th>
                <th className="px-3 py-2 text-left font-semibold text-gray-600">
                  ID
                </th>
                <th className="px-3 py-2 text-left font-semibold text-gray-600">
                  Group
                </th>
                <th className="px-3 py-2 text-left font-semibold text-gray-600">
                  Fields
                </th>
                <th className="px-3 py-2 text-left font-semibold text-gray-600">
                  Resources
                </th>
                <th className="px-3 py-2 text-left font-semibold text-gray-600">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100 bg-white">
              {filtered.map((t) => (
                <tr key={t.id} className="hover:bg-gray-50">
                  <td className="px-3 py-2 font-medium text-gray-800">
                    {t.title || t.id}
                  </td>
                  <td className="px-3 py-2 text-gray-700">{t.id}</td>
                  <td className="px-3 py-2 text-gray-700">{t.group || "-"}</td>
                  <td className="px-3 py-2 text-gray-700">{t.fields.length}</td>
                  <td className="px-3 py-2 text-gray-700">
                    {counts[t.id] ?? 0}
                  </td>
                  <td className="px-3 py-2">
                    <div className="flex items-center gap-3">
                      <ActionLink tone="primary" to={`/resources/${t.id}`}>
                        Resources
                      </ActionLink>
                      <ActionLink to={`/resource-types/${t.id}/edit`}>
                        Edit
                      </ActionLink>
                      <ActionButton
                        tone="danger"
                        onClick={async () => {
                          if (
                            !window.confirm(`Delete Resource Type '${t.id}'?`)
                          )
                            return;
                          try {
                            await api.deleteResourceType(t.id);
                            setItems((prev) =>
                              prev.filter((x) => x.id !== t.id),
                            );
                          } catch (e) {
                            setError(
                              e instanceof Error ? e.message : String(e),
                            );
                          }
                        }}
                      >
                        Delete
                      </ActionButton>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </DataCard>
    </section>
  );
}
