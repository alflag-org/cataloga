import { useState } from "react";
import type { FieldDef } from "../types";
import { SelectInput } from "./SelectInput";
import { TextInput } from "./TextInput";
import { TextareaInput } from "./TextareaInput";

type ReferenceOption = { id: string; name: string };

type Props = {
  field: FieldDef;
  value: unknown;
  onChange: (value: unknown) => void;
  reference?: {
    multiple: boolean;
    options: ReferenceOption[];
  };
};

function asText(value: unknown): string {
  if (value == null) return "";
  if (typeof value === "string") return value;
  try {
    return JSON.stringify(value, null, 2);
  } catch {
    return String(value);
  }
}

function matchesQuery(opt: ReferenceOption, query: string): boolean {
  const q = query.trim().toLowerCase();
  if (!q) return true;
  return opt.id.toLowerCase().includes(q) || opt.name.toLowerCase().includes(q);
}

function resolveReferenceId(input: string, options: ReferenceOption[]): string {
  const trimmed = input.trim();
  if (!trimmed) return "";
  const exactId = options.find((opt) => opt.id === trimmed);
  if (exactId) return exactId.id;
  const exactName = options.find((opt) => opt.name === trimmed);
  if (exactName) return exactName.id;
  return trimmed;
}

export function FieldInput({ field, value, onChange, reference }: Props) {
  const base = `field-${field.name}`;
  const currentText = asText(value);
  const [draft, setDraft] = useState("");

  if (field.type === "reference" && reference && !reference.multiple) {
    const suggestions = reference.options
      .filter((opt) => matchesQuery(opt, currentText))
      .slice(0, 8);
    const currentMeta = reference.options.find((opt) => opt.id === currentText);
    return (
      <div className="space-y-2">
        <TextInput
          id={base}
          type="text"
          placeholder="Search by resource id or name"
          value={currentText}
          onChange={(e) =>
            onChange(resolveReferenceId(e.target.value, reference.options))
          }
        />
        {suggestions.length > 0 ? (
          <div className="max-h-40 overflow-auto rounded-md border border-gray-200 bg-white">
            {suggestions.map((opt) => (
              <button
                key={opt.id}
                type="button"
                className="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50"
                onClick={() => onChange(opt.id)}
              >
                {opt.name} ({opt.id})
              </button>
            ))}
          </div>
        ) : null}
        {currentMeta ? (
          <p className="text-xs text-gray-500">
            Selected: {currentMeta.name} ({currentMeta.id})
          </p>
        ) : null}
      </div>
    );
  }

  if (field.type === "reference" && !reference) {
    return (
      <TextInput
        id={base}
        type="text"
        value={currentText}
        onChange={(e) => onChange(e.target.value)}
      />
    );
  }

  if (field.type === "reference_array" && reference && reference.multiple) {
    const selected = Array.isArray(value) ? value.map(String) : [];
    const suggestions = reference.options
      .filter((opt) => !selected.includes(opt.id))
      .filter((opt) => matchesQuery(opt, draft))
      .slice(0, 8);

    const addReference = (input: string) => {
      const resolved = resolveReferenceId(input, reference.options);
      if (!resolved || selected.includes(resolved)) {
        setDraft("");
        return;
      }
      onChange([...selected, resolved]);
      setDraft("");
    };

    return (
      <div className="space-y-2 rounded-md border border-gray-200 p-3">
        <div className="flex items-center gap-2">
          <TextInput
            id={base}
            type="text"
            placeholder="Search by resource id or name"
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter") {
                e.preventDefault();
                addReference(draft);
              }
            }}
          />
          <button
            type="button"
            className="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
            onClick={() => addReference(draft)}
          >
            Add
          </button>
        </div>
        {suggestions.length > 0 ? (
          <div className="max-h-40 overflow-auto rounded-md border border-gray-200 bg-white">
            {suggestions.map((opt) => (
              <button
                key={opt.id}
                type="button"
                className="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50"
                onClick={() => addReference(opt.id)}
              >
                {opt.name} ({opt.id})
              </button>
            ))}
          </div>
        ) : null}
        {selected.length === 0 ? (
          <p className="text-xs text-gray-500">No selected resources.</p>
        ) : (
          <div className="flex flex-wrap gap-2">
            {selected.map((id) => {
              const meta = reference.options.find((opt) => opt.id === id);
              return (
                <button
                  key={id}
                  type="button"
                  className="rounded-full border border-gray-300 bg-white px-3 py-1 text-xs text-gray-700 hover:bg-gray-50"
                  onClick={() =>
                    onChange(selected.filter((selectedId) => selectedId !== id))
                  }
                  title="Remove"
                >
                  {meta ? `${meta.name} (${id})` : id} ×
                </button>
              );
            })}
          </div>
        )}
      </div>
    );
  }

  if (
    field.type === "text" ||
    field.type === "json" ||
    field.type === "array" ||
    field.type === "reference_array"
  ) {
    return (
      <TextareaInput
        id={base}
        value={currentText}
        onChange={(e) => onChange(e.target.value)}
        rows={4}
      />
    );
  }
  if (field.type === "boolean") {
    return (
      <SelectInput
        id={base}
        value={String(Boolean(value))}
        onChange={(e) => onChange(e.target.value === "true")}
      >
        <option value="false">False</option>
        <option value="true">True</option>
      </SelectInput>
    );
  }
  if (field.type === "enum") {
    return (
      <SelectInput
        id={base}
        value={currentText}
        onChange={(e) => onChange(e.target.value)}
      >
        <option value="">Select</option>
        {field.enum_values.map((opt) => (
          <option key={opt} value={opt}>
            {opt}
          </option>
        ))}
      </SelectInput>
    );
  }
  if (field.type === "integer" || field.type === "number") {
    return (
      <TextInput
        id={base}
        type="number"
        value={currentText}
        onChange={(e) => onChange(e.target.value)}
      />
    );
  }
  if (field.type === "url") {
    return (
      <TextInput
        id={base}
        type="url"
        placeholder="https://example.com"
        value={currentText}
        onChange={(e) => onChange(e.target.value)}
      />
    );
  }
  if (field.type === "ip") {
    return (
      <TextInput
        id={base}
        type="text"
        placeholder="10.10.10.20"
        value={currentText}
        onChange={(e) => onChange(e.target.value)}
      />
    );
  }
  if (field.type === "cidr") {
    return (
      <TextInput
        id={base}
        type="text"
        placeholder="10.10.10.0/24"
        value={currentText}
        onChange={(e) => onChange(e.target.value)}
      />
    );
  }
  return (
    <TextInput
      id={base}
      type="text"
      value={currentText}
      onChange={(e) => onChange(e.target.value)}
    />
  );
}
