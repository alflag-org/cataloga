import { ActionButton, ActionLink } from "./Action";
import { readPath, type NormalizedListColumn, type Resource } from "../types";
import { useI18n } from "../i18n";

type Props = {
  type: string;
  columns: NormalizedListColumn[];
  rows: Resource[];
  sortBy: string;
  sortDir: "asc" | "desc";
  onSort: (column: string) => void;
  onDelete: (resourceId: string) => void;
};

function toCompact(value: unknown): string {
  if (Array.isArray(value)) return value.join(", ");
  if (value && typeof value === "object") return JSON.stringify(value);
  if (value == null) return "";
  return String(value);
}

export function ResourceListTable({
  type,
  columns,
  rows,
  sortBy,
  sortDir,
  onSort,
  onDelete,
}: Props) {
  const { t } = useI18n();
  return (
    <div className="overflow-x-auto">
      <table className="min-w-full divide-y divide-gray-200 text-sm">
        <thead className="bg-gray-50">
          <tr>
            {columns.map((c) => (
              <th
                key={c.path}
                className="px-3 py-2 text-left font-semibold text-gray-600"
              >
                <button
                  className="inline-flex items-center gap-1 hover:text-gray-900"
                  onClick={() => onSort(c.path)}
                >
                  {c.label}
                  {sortBy === c.path ? (sortDir === "asc" ? "↑" : "↓") : ""}
                </button>
              </th>
            ))}
            <th className="px-3 py-2 text-left font-semibold text-gray-600">
              {t("Actions")}
            </th>
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-100 bg-white">
          {rows.map((r) => (
            <tr key={r.metadata.id} className="hover:bg-gray-50">
              {columns.map((c) => (
                <td
                  key={c.path}
                  className="max-w-xs px-3 py-2 align-top text-gray-700"
                >
                  <span className="line-clamp-2 break-all">
                    {toCompact(readPath(r, c.path))}
                  </span>
                </td>
              ))}
              <td className="px-3 py-2">
                <div className="flex items-center gap-3">
                  <ActionLink
                    tone="primary"
                    to={`/resources/${type}/${r.metadata.id}`}
                  >
                    {t("Show")}
                  </ActionLink>
                  <ActionLink to={`/resources/${type}/${r.metadata.id}/edit`}>
                    {t("Edit")}
                  </ActionLink>
                  <ActionButton
                    tone="danger"
                    onClick={() => onDelete(r.metadata.id)}
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
  );
}
