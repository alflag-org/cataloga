import { hashString } from "./hash";

const GROUP_COLOR_MAP: Record<string, string> = {
  Network: "#2563eb",
  Compute: "#16a34a",
  DNS: "#9333ea",
  Services: "#ea580c",
  Organization: "#0891b2",
  Other: "#64748b",
};

export function normalizeGroupName(group: string): string {
  const trimmed = group.trim();
  return trimmed || "Other";
}

export function pickGroupColor(group: string): string {
  const normalized = normalizeGroupName(group);
  return GROUP_COLOR_MAP[normalized] ?? GROUP_COLOR_MAP.Other;
}

const TYPE_COLOR_PALETTE = [
  "#2563eb",
  "#16a34a",
  "#ea580c",
  "#0891b2",
  "#9333ea",
  "#dc2626",
  "#0d9488",
  "#ca8a04",
  "#4f46e5",
  "#65a30d",
  "#be123c",
  "#0369a1",
];

export function pickTypeColor(typeId: string): string {
  const normalized = typeId.trim().toLowerCase();
  if (!normalized) return GROUP_COLOR_MAP.Other;
  const index = Math.abs(hashString(normalized)) % TYPE_COLOR_PALETTE.length;
  return TYPE_COLOR_PALETTE[index];
}
