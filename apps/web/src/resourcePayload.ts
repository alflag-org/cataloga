import type { Resource, ResourceType } from "./types";

function parseFieldValue(type: string, raw: unknown): unknown {
  if (raw == null) return raw;
  if (type === "integer") {
    const n = Number(raw);
    return Number.isFinite(n) ? Math.trunc(n) : 0;
  }
  if (type === "number") {
    const n = Number(raw);
    return Number.isFinite(n) ? n : 0;
  }
  if (type === "boolean") return Boolean(raw);
  if (type === "array") {
    if (Array.isArray(raw)) return raw;
    const s = String(raw).trim();
    if (!s) return [];
    return JSON.parse(s);
  }
  if (type === "json") {
    if (typeof raw === "object") return raw;
    const s = String(raw).trim();
    if (!s) return {};
    return JSON.parse(s);
  }
  if (type === "reference_array") {
    if (Array.isArray(raw)) return raw.map(String);
    const s = String(raw).trim();
    if (!s) return [];
    try {
      return JSON.parse(s);
    } catch {
      return s
        .split("\n")
        .map((x) => x.trim())
        .filter(Boolean);
    }
  }
  return String(raw);
}

export function buildResourcePayload(
  resourceType: ResourceType,
  form: Resource,
  customFieldsText: string,
  dependenciesText: string,
): Resource {
  const required = new Set(resourceType.required_fields);
  const spec: Record<string, unknown> = {};

  for (const field of resourceType.fields) {
    const raw = form.spec[field.name];
    if (raw == null || raw === "") {
      if (required.has(field.name)) {
        throw new Error(`missing required field: ${field.name}`);
      }
      continue;
    }
    spec[field.name] = parseFieldValue(field.type, raw);
  }

  return {
    ...form,
    type: resourceType.id,
    spec,
    custom_fields: JSON.parse(customFieldsText || "{}") as Record<
      string,
      unknown
    >,
    dependencies: JSON.parse(dependenciesText || "{}") as Record<
      string,
      unknown
    >,
  };
}
