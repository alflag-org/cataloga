export type FieldType =
  | "string"
  | "text"
  | "integer"
  | "number"
  | "boolean"
  | "enum"
  | "array"
  | "json"
  | "reference"
  | "reference_array"
  | "ip"
  | "cidr"
  | "url";

export type FieldDef = {
  name: string;
  label: string;
  type: FieldType;
  enum_values: string[];
};

export type ResourceType = {
  id: string;
  title: string;
  group: string;
  description: string;
  fields: FieldDef[];
  required_fields: string[];
  list_columns: Array<string | { path: string; label?: string }>;
  form_layout: Array<{ title: string; fields: string[] }>;
  detail_sections: Array<{ title: string; fields: string[] }>;
  references: Array<{ field: string; target_type: string; multiple: boolean }>;
  validation_rules: Array<Record<string, unknown>>;
};

export type NormalizedListColumn = {
  path: string;
  label: string;
};

export type Resource = {
  api_version: string;
  kind: string;
  metadata: {
    id: string;
    type: string;
    name: string;
    tags: Record<string, string>;
  };
  spec: Record<string, unknown>;
  custom_fields: Record<string, unknown>;
  dependencies: Record<string, unknown>;
};

export type ValidationIssue = {
  severity: string;
  resource_type: string;
  resource_id: string;
  field: string;
  message: string;
};

export type ValidationResult = {
  status: "ok" | "failed";
  errors: ValidationIssue[];
  warnings: ValidationIssue[];
};

export type ResourceRef = {
  resource_type: string;
  resource_id: string;
  name: string;
  field: string;
};

export type ResourceReferences = {
  outgoing: ResourceRef[];
  incoming: ResourceRef[];
};

export type ImportPreviewResult = {
  resource_types_to_create: string[];
  resource_types_to_update: string[];
  resources_to_create: string[];
  resources_to_update: string[];
  validation_errors: ValidationIssue[];
};

export function defaultResourceType(): ResourceType {
  return {
    id: "",
    title: "",
    group: "",
    description: "",
    fields: [],
    required_fields: [],
    list_columns: ["metadata.name"],
    form_layout: [],
    detail_sections: [],
    references: [],
    validation_rules: [],
  };
}

export function defaultResource(type: string): Resource {
  return {
    api_version: "cataloga.io/v1",
    kind: "Resource",
    metadata: {
      id: "",
      type,
      name: "",
      tags: {},
    },
    spec: {},
    custom_fields: {},
    dependencies: {},
  };
}

export function compactValue(value: unknown): string {
  if (value == null) return "";
  if (typeof value === "string") return value;
  if (typeof value === "number" || typeof value === "boolean")
    return String(value);
  try {
    return JSON.stringify(value);
  } catch {
    return String(value);
  }
}

export function readPath(resource: Resource, path: string): string {
  const [head, ...rest] = path.split(".");
  const root =
    head === "metadata"
      ? resource.metadata
      : head === "spec"
        ? resource.spec
        : head === "custom_fields"
          ? resource.custom_fields
          : head === "dependencies"
            ? resource.dependencies
            : undefined;
  if (!root) return "";
  let cur: unknown = root;
  for (const segment of rest) {
    if (segment === "*") {
      return compactValue(cur);
    }
    if (cur == null || typeof cur !== "object") return "";
    cur = (cur as Record<string, unknown>)[segment];
  }
  return compactValue(cur);
}

const ACRONYMS = new Set([
  "id",
  "ip",
  "url",
  "dns",
  "vlan",
  "cidr",
  "vm",
  "os",
  "cpu",
  "ram",
]);

export function deriveDisplayLabel(path: string): string {
  const stripped = path
    .replace(/^metadata\.tags\./, "")
    .replace(/^metadata\./, "")
    .replace(/^spec\./, "")
    .replace(/^custom_fields\./, "")
    .replace(/^dependencies\./, "");
  const words = stripped
    .split(/[._-]+/)
    .filter(Boolean)
    .map((word) => {
      const lower = word.toLowerCase();
      if (ACRONYMS.has(lower)) return lower.toUpperCase();
      return lower.slice(0, 1).toUpperCase() + lower.slice(1);
    });
  return words.join(" ") || path;
}

export function normalizeListColumn(
  column: string | { path: string; label?: string },
): NormalizedListColumn {
  if (typeof column === "string") {
    return { path: column, label: deriveDisplayLabel(column) };
  }
  return {
    path: column.path,
    label: column.label?.trim() || deriveDisplayLabel(column.path),
  };
}

export function normalizeListColumns(
  columns: Array<string | { path: string; label?: string }>,
): NormalizedListColumn[] {
  return columns.map(normalizeListColumn).filter((c) => Boolean(c.path.trim()));
}
