import type { FieldDef } from "../types";
import { ReferencePicker, type ReferenceOption } from "./ReferencePicker";
import { SelectInput } from "./SelectInput";
import { TextInput } from "./TextInput";
import { TextareaInput } from "./TextareaInput";

type Props = {
  field: FieldDef;
  value: unknown;
  onChange: (value: unknown) => void;
  reference?: {
    multiple: boolean;
    targetType: string;
    targetTypeTitle?: string;
    options: ReferenceOption[];
    loading?: boolean;
    error?: string | null;
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
  const currentText = asText(value);

  if (field.type === "reference" && reference) {
    return (
      <ReferencePicker
        targetType={reference.targetType}
        targetTypeTitle={reference.targetTypeTitle}
        multiple={false}
        value={typeof value === "string" ? value : null}
        options={reference.options}
        loading={reference.loading}
        error={reference.error}
        createTo={`/resources/${reference.targetType}/new`}
        onChange={onChange}
      />
    );
  }

  if (field.type === "reference" && !reference) {
    return (
      <div className="space-y-2">
        <p className="text-xs text-amber-700">
          Reference target is not configured for this field.
        </p>
        <TextInput
          id={base}
          type="text"
          value={currentText}
          onChange={(e) => onChange(e.target.value)}
        />
      </div>
    );
  }

  if (field.type === "reference_array" && reference) {
    return (
      <ReferencePicker
        targetType={reference.targetType}
        targetTypeTitle={reference.targetTypeTitle}
        multiple
        value={Array.isArray(value) ? value.map(String) : []}
        options={reference.options}
        loading={reference.loading}
        error={reference.error}
        createTo={`/resources/${reference.targetType}/new`}
        onChange={onChange}
      />
    );
  }

  if (field.type === "reference_array" && !reference) {
    return (
      <div className="space-y-2">
        <p className="text-xs text-amber-700">
          Reference target is not configured for this field.
        </p>
        <TextareaInput
          id={base}
          value={currentText}
          onChange={(e) => onChange(e.target.value)}
          rows={4}
        />
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
