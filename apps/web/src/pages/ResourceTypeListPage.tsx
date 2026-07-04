import { useEffect, useState } from "react";
import { api } from "../api/client";
import { Button, LinkButton } from "../components/Button";
import { ActionButton, ActionLink } from "../components/Action";
import { DataCard } from "../components/DataCard";
import { ErrorBanner } from "../components/ErrorBanner";
import { PageHeader } from "../components/PageHeader";
import { TextInput } from "../components/TextInput";
import { useI18n } from "../i18n";
import {
  canSubmitResourceTypeDelete,
  shouldDeleteResourcesWithResourceType,
} from "../resourceTypeDeletion";
import type { ResourceType } from "../types";

export function ResourceTypeListPage() {
  const { t, tf } = useI18n();
  const [items, setItems] = useState<ResourceType[]>([]);
  const [counts, setCounts] = useState<Record<string, number>>({});
  const [query, setQuery] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<ResourceType | null>(null);
  const [deleteConfirmation, setDeleteConfirmation] = useState("");
  const [isDeleting, setIsDeleting] = useState(false);

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
  const deleteResourceCount = deleteTarget ? (counts[deleteTarget.id] ?? 0) : 0;
  const canDelete = canSubmitResourceTypeDelete({
    targetId: deleteTarget?.id ?? "",
    confirmation: deleteConfirmation,
    isDeleting,
  });

  async function submitDeleteResourceType() {
    if (!deleteTarget || !canDelete) return;
    const targetId = deleteTarget.id;
    setError(null);
    setIsDeleting(true);
    try {
      await api.deleteResourceType(targetId, {
        deleteResources:
          shouldDeleteResourcesWithResourceType(deleteResourceCount),
      });
      setItems((prev) => prev.filter((x) => x.id !== targetId));
      setCounts((prev) => {
        const next = { ...prev };
        delete next[targetId];
        return next;
      });
      setDeleteTarget(null);
      setDeleteConfirmation("");
    } catch (e) {
      setError(e instanceof Error ? e.message : String(e));
    } finally {
      setIsDeleting(false);
    }
  }

  return (
    <section className="space-y-5">
      <PageHeader
        title={t("Administration / Resource Types")}
        actions={
          <LinkButton to="/resource-types/new">
            {t("Create Resource Type")}
          </LinkButton>
        }
      />
      <ErrorBanner message={error} />
      <DataCard>
        <div className="mb-4">
          <TextInput
            placeholder={t("Search")}
            value={query}
            onChange={(e) => setQuery(e.target.value)}
          />
        </div>
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200 text-sm">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-3 py-2 text-left font-semibold text-gray-600">
                  {t("Title")}
                </th>
                <th className="px-3 py-2 text-left font-semibold text-gray-600">
                  {t("ID")}
                </th>
                <th className="px-3 py-2 text-left font-semibold text-gray-600">
                  {t("Group")}
                </th>
                <th className="px-3 py-2 text-left font-semibold text-gray-600">
                  {t("Fields")}
                </th>
                <th className="px-3 py-2 text-left font-semibold text-gray-600">
                  {t("Resources")}
                </th>
                <th className="px-3 py-2 text-left font-semibold text-gray-600">
                  {t("Actions")}
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100 bg-white">
              {filtered.map((item) => (
                <tr key={item.id} className="hover:bg-gray-50">
                  <td className="px-3 py-2 font-medium text-gray-800">
                    {item.title || item.id}
                  </td>
                  <td className="px-3 py-2 text-gray-700">{item.id}</td>
                  <td className="px-3 py-2 text-gray-700">
                    {item.group || "-"}
                  </td>
                  <td className="px-3 py-2 text-gray-700">
                    {item.fields.length}
                  </td>
                  <td className="px-3 py-2 text-gray-700">
                    {counts[item.id] ?? 0}
                  </td>
                  <td className="px-3 py-2">
                    <div className="flex items-center gap-3">
                      <ActionLink tone="primary" to={`/resources/${item.id}`}>
                        {t("Show")}
                      </ActionLink>
                      <ActionLink to={`/resource-types/${item.id}/edit`}>
                        {t("Edit")}
                      </ActionLink>
                      <ActionButton
                        tone="danger"
                        onClick={() => {
                          setError(null);
                          setDeleteTarget(item);
                          setDeleteConfirmation("");
                        }}
                      >
                        {t("Delete")}
                      </ActionButton>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </DataCard>
      {deleteTarget ? (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 px-4"
          role="dialog"
          aria-modal="true"
          aria-labelledby="delete-resource-type-title"
        >
          <form
            className="w-full max-w-md rounded-lg bg-white p-5 shadow-xl"
            onSubmit={(e) => {
              e.preventDefault();
              void submitDeleteResourceType();
            }}
          >
            <h2
              id="delete-resource-type-title"
              className="text-base font-semibold text-gray-900"
            >
              {tf("Delete Resource Type '{id}'?", {
                id: deleteTarget.id,
              })}
            </h2>
            <p className="mt-3 text-sm text-gray-700">
              {deleteResourceCount > 0
                ? tf("Delete {count} Resources with this Resource Type.", {
                    count: String(deleteResourceCount),
                  })
                : t("This Resource Type has no Resources.")}
            </p>
            <label className="mt-4 block text-sm font-medium text-gray-700">
              {t("Type Resource Type ID to confirm")}
              <TextInput
                className="mt-1"
                autoFocus
                value={deleteConfirmation}
                onChange={(e) => setDeleteConfirmation(e.target.value)}
              />
            </label>
            {deleteConfirmation && deleteConfirmation !== deleteTarget.id ? (
              <p className="mt-2 text-sm text-red-700">
                {t("Confirmation must match Resource Type ID.")}
              </p>
            ) : null}
            {error ? (
              <p className="mt-2 break-words text-sm text-red-700">{error}</p>
            ) : null}
            <div className="mt-5 flex justify-end gap-2">
              <Button
                type="button"
                variant="secondary"
                disabled={isDeleting}
                onClick={() => {
                  setDeleteTarget(null);
                  setDeleteConfirmation("");
                }}
              >
                {t("Discard")}
              </Button>
              <Button type="submit" variant="danger" disabled={!canDelete}>
                {t("Delete")}
              </Button>
            </div>
          </form>
        </div>
      ) : null}
    </section>
  );
}
