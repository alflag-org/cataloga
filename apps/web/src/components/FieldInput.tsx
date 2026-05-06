import type { FieldDef } from "../types";
import { SelectInput } from "./SelectInput";
import { TextInput } from "./TextInput";
import { TextareaInput } from "./TextareaInput";

type Props = {
  field: FieldDef;
  value: unknown;
  onChange: (value: unknown) => void;
  reference?: {
    multiple: boolean;
    options: Array<{ id: string; name: string }>;
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

export function FieldInput({ field, value, onChange, reference }: Props) {
  const base = `field-${field.name}`;
  if (field.type === "reference" && reference && !reference.multiple) {
    return (
      <SelectInput
        id={base}
        value={asText(value)}
        onChange={(e) => onChange(e.target.value)}
      >
        <option value="">Select</option>
        {reference.options.map((opt) => (
          <option key={opt.id} value={opt.id}>
            {opt.name} ({opt.id})
          </option>
        ))}
      </SelectInput>
    );
  }
  if (field.type === "reference_array" && reference && reference.multiple) {
    const selected = Array.isArray(value) ? value.map(String) : [];
    return (
      <div className="space-y-2 rounded-md border border-gray-200 p-3">
        {reference.options.length === 0 ? (
          <p className="text-xs text-gray-500">
            No available target resources.
          </p>
        ) : (
          reference.options.map((opt) => (
            <label
              key={opt.id}
              className="flex items-center gap-2 text-sm text-gray-700"
            >
              <input
                type="checkbox"
                checked={selected.includes(opt.id)}
                onChange={(e) => {
                  if (e.target.checked) {
                    onChange([...selected, opt.id]);
                  } else {
                    onChange(selected.filter((id) => id !== opt.id));
                  }
                }}
              />
              <span>
                {opt.name} ({opt.id})
              </span>
            </label>
          ))
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
        value={asText(value)}
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
        value={asText(value)}
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
        value={asText(value)}
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
        value={asText(value)}
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
        value={asText(value)}
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
        value={asText(value)}
        onChange={(e) => onChange(e.target.value)}
      />
    );
  }
  return (
    <TextInput
      id={base}
      type="text"
      value={asText(value)}
      onChange={(e) => onChange(e.target.value)}
    />
  );
}
