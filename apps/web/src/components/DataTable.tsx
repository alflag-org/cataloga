import { type ReactNode, useMemo } from "react";
import { useI18n } from "../i18n";
import { TextInput } from "./TextInput";
import { SelectInput } from "./SelectInput";

export type DataTableColumn<T> = {
  key: string;
  label: string;
  render?: (row: T) => ReactNode;
  sortValue?: (row: T) => string | number;
  className?: string;
};

type Props<T> = {
  columns: DataTableColumn<T>[];
  rows: T[];
  searchValue: string;
  onSearchChange: (value: string) => void;
  sortKey: string;
  sortDir: "asc" | "desc";
  onSort: (key: string) => void;
  searchPlaceholder?: string;
  filters?: ReactNode;
  rowKey: (row: T, index: number) => string;
  empty?: ReactNode;
};

function defaultSortValue(value: unknown): string {
  if (value == null) return "";
  if (typeof value === "number") return String(value);
  if (typeof value === "string") return value;
  return JSON.stringify(value);
}

export function DataTable<T>({
  columns,
  rows,
  searchValue,
  onSearchChange,
  sortKey,
  sortDir,
  onSort,
  searchPlaceholder,
  filters,
  rowKey,
  empty,
}: Props<T>) {
  const { t } = useI18n();
  const sortedRows = useMemo(() => {
    const target = columns.find((col) => col.key === sortKey);
    if (!target) return rows;
    return [...rows].sort((a, b) => {
      const av = target.sortValue
        ? target.sortValue(a)
        : defaultSortValue(target.render ? target.render(a) : "");
      const bv = target.sortValue
        ? target.sortValue(b)
        : defaultSortValue(target.render ? target.render(b) : "");
      if (av === bv) return 0;
      const order = av < bv ? -1 : 1;
      return sortDir === "asc" ? order : -order;
    });
  }, [columns, rows, sortKey, sortDir]);

  return (
    <div className="space-y-3">
      <div className="grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto] md:items-end">
        <label className="text-sm text-gray-700">
          {t("Search")}
          <TextInput
            value={searchValue}
            placeholder={searchPlaceholder ?? t("Search")}
            onChange={(e) => onSearchChange(e.target.value)}
          />
        </label>
        {filters ? (
          <div className="grid grid-cols-1 gap-3 md:grid-cols-2">{filters}</div>
        ) : null}
      </div>

      {sortedRows.length === 0 ? (
        (empty ?? <p className="text-sm text-gray-600">{t("No rows.")}</p>)
      ) : (
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200 text-sm">
            <thead className="bg-gray-50">
              <tr>
                {columns.map((column) => (
                  <th
                    key={column.key}
                    className="px-3 py-2 text-left font-semibold text-gray-600"
                  >
                    <button
                      className="inline-flex items-center gap-1 hover:text-gray-900"
                      onClick={() => onSort(column.key)}
                    >
                      {column.label}
                      {sortKey === column.key
                        ? sortDir === "asc"
                          ? "↑"
                          : "↓"
                        : ""}
                    </button>
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100 bg-white">
              {sortedRows.map((row, index) => (
                <tr key={rowKey(row, index)} className="hover:bg-gray-50">
                  {columns.map((column) => (
                    <td
                      key={column.key}
                      className={`px-3 py-2 text-gray-700 ${column.className ?? ""}`}
                    >
                      {column.render ? column.render(row) : ""}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

export function FilterSelect({
  label,
  value,
  onChange,
  options,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  options: Array<{ value: string; label: string }>;
}) {
  return (
    <label className="text-sm text-gray-700">
      {label}
      <SelectInput value={value} onChange={(e) => onChange(e.target.value)}>
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </SelectInput>
    </label>
  );
}
