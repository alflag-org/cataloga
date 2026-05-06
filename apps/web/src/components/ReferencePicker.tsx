import { useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { TextInput } from "./TextInput";

export type ReferenceOption = {
  id: string;
  name: string;
  typeId: string;
  typeTitle: string;
  description?: string;
};

type ReferencePickerProps = {
  targetType: string;
  targetTypeTitle?: string;
  multiple: boolean;
  value: string | string[] | null | undefined;
  options: ReferenceOption[];
  loading?: boolean;
  error?: string | null;
  createTo?: string;
  onChange: (value: string | string[] | null) => void;
};

function matches(option: ReferenceOption, query: string): boolean {
  const q = query.trim().toLowerCase();
  if (!q) return true;
  return (
    option.id.toLowerCase().includes(q) ||
    option.name.toLowerCase().includes(q) ||
    option.typeTitle.toLowerCase().includes(q) ||
    (option.description ?? "").toLowerCase().includes(q)
  );
}

function typeLabel(targetType: string, targetTypeTitle?: string): string {
  return targetTypeTitle?.trim() || targetType;
}

export function ReferencePicker({
  targetType,
  targetTypeTitle,
  multiple,
  value,
  options,
  loading = false,
  error = null,
  createTo,
  onChange,
}: ReferencePickerProps) {
  const [query, setQuery] = useState("");
  const [open, setOpen] = useState(false);
  const [highlightedIndex, setHighlightedIndex] = useState(0);
  const targetLabel = typeLabel(targetType, targetTypeTitle);

  const selectedIds = Array.isArray(value)
    ? value.map(String)
    : value
      ? [String(value)]
      : [];
  const selected = selectedIds
    .map((id) => ({ id, option: options.find((option) => option.id === id) }))
    .filter((item) => Boolean(item.id));

  const filtered = useMemo(() => {
    const excluded = new Set(multiple ? selectedIds : []);
    return options
      .filter((option) => !excluded.has(option.id))
      .filter((option) => matches(option, query))
      .slice(0, 20);
  }, [multiple, options, query, selectedIds]);

  const selectOne = (id: string) => {
    onChange(id);
    setQuery("");
    setOpen(false);
    setHighlightedIndex(0);
  };

  const addMany = (id: string) => {
    const current = new Set(selectedIds);
    current.add(id);
    onChange([...current]);
    setQuery("");
    setOpen(false);
    setHighlightedIndex(0);
  };

  const removeMany = (id: string) => {
    onChange(selectedIds.filter((selectedId) => selectedId !== id));
  };

  const hasUnknownSingle =
    !multiple && selectedIds.length === 1 && !selected[0]?.option;

  const canShowOptions = open && !loading && !error;

  return (
    <div className="space-y-2">
      {!multiple ? (
        <>
          {selected[0]?.option ? (
            <div className="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2">
              <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">
                Selected
              </p>
              <div className="mt-1 flex items-start justify-between gap-3">
                <div>
                  <p className="text-sm font-medium text-gray-900">
                    {selected[0].option.name}
                  </p>
                  <p className="text-xs text-gray-500">
                    {targetLabel} / {selected[0].option.id}
                  </p>
                  {selected[0].option.description ? (
                    <p className="mt-1 line-clamp-2 text-xs text-gray-500">
                      {selected[0].option.description}
                    </p>
                  ) : null}
                </div>
                <button
                  type="button"
                  className="text-xs text-gray-500 hover:text-red-600"
                  onClick={() => onChange(null)}
                >
                  Clear
                </button>
              </div>
            </div>
          ) : hasUnknownSingle ? (
            <div className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2">
              <p className="text-sm font-medium text-amber-900">
                Unknown target: {selectedIds[0]}
              </p>
              <p className="text-xs text-amber-800">
                This Resource references a missing {targetLabel}.
              </p>
              <button
                type="button"
                className="mt-2 text-xs font-medium text-amber-900 hover:text-red-700"
                onClick={() => onChange(null)}
              >
                Clear
              </button>
            </div>
          ) : (
            <p className="text-xs text-gray-500">No {targetLabel} selected.</p>
          )}
        </>
      ) : selected.length > 0 ? (
        <div className="flex flex-wrap gap-2">
          {selected.map(({ id, option }) => (
            <button
              key={id}
              type="button"
              className="rounded-full border border-gray-300 bg-white px-3 py-1 text-xs text-gray-700 hover:border-red-300 hover:text-red-600"
              onClick={() => removeMany(id)}
              title="Remove"
            >
              {option ? `${option.name} (${id})` : `Unknown: ${id}`} ×
            </button>
          ))}
        </div>
      ) : (
        <p className="text-xs text-gray-500">No selected resources.</p>
      )}

      <div
        className="relative"
        onBlur={(e) => {
          if (!e.currentTarget.contains(e.relatedTarget as Node | null)) {
            setOpen(false);
          }
        }}
      >
        <TextInput
          value={query}
          placeholder={`Search ${targetLabel} by name or ID`}
          onFocus={() => setOpen(true)}
          onChange={(e) => {
            setQuery(e.target.value);
            setOpen(true);
            setHighlightedIndex(0);
          }}
          onKeyDown={(e) => {
            if (!canShowOptions) {
              if (e.key === "Escape") setOpen(false);
              return;
            }
            if (e.key === "Escape") {
              e.preventDefault();
              setOpen(false);
            } else if (e.key === "ArrowDown") {
              e.preventDefault();
              setHighlightedIndex((prev) =>
                Math.min(prev + 1, Math.max(filtered.length - 1, 0)),
              );
            } else if (e.key === "ArrowUp") {
              e.preventDefault();
              setHighlightedIndex((prev) => Math.max(prev - 1, 0));
            } else if (e.key === "Enter" && filtered[highlightedIndex]) {
              e.preventDefault();
              if (multiple) addMany(filtered[highlightedIndex].id);
              else selectOne(filtered[highlightedIndex].id);
            }
          }}
        />

        {open ? (
          <div className="absolute z-10 mt-1 w-full rounded-xl border border-gray-200 bg-white shadow-lg">
            {loading ? (
              <p className="px-3 py-3 text-sm text-gray-600">
                Loading {targetLabel} resources...
              </p>
            ) : error ? (
              <p className="px-3 py-3 text-sm text-red-700">
                Failed to load {targetLabel} resources.
              </p>
            ) : filtered.length > 0 ? (
              <ul className="max-h-72 overflow-auto py-1">
                {filtered.map((option, index) => (
                  <li key={option.id}>
                    <button
                      type="button"
                      className={`block w-full px-3 py-2 text-left ${
                        index === highlightedIndex
                          ? "bg-gray-100"
                          : "hover:bg-gray-50"
                      }`}
                      onMouseDown={(e) => e.preventDefault()}
                      onClick={() =>
                        multiple ? addMany(option.id) : selectOne(option.id)
                      }
                    >
                      <p className="text-sm font-medium text-gray-900">
                        {option.name}
                      </p>
                      <p className="text-xs text-gray-500">
                        {option.typeTitle || option.typeId} / {option.id}
                      </p>
                      {option.description ? (
                        <p className="mt-1 line-clamp-2 text-xs text-gray-500">
                          {option.description}
                        </p>
                      ) : null}
                    </button>
                  </li>
                ))}
              </ul>
            ) : (
              <div className="px-3 py-3 text-sm">
                <p className="text-gray-600">
                  {options.length === 0
                    ? `No ${targetLabel} resources.`
                    : `No matching ${targetLabel} resources.`}
                </p>
                {createTo ? (
                  <Link
                    to={createTo}
                    className="mt-2 inline-block text-blue-700 hover:text-blue-800"
                  >
                    Create {targetLabel}
                  </Link>
                ) : null}
              </div>
            )}
          </div>
        ) : null}
      </div>
    </div>
  );
}
