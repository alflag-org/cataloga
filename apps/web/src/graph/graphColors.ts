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
