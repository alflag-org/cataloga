import { DataCard } from "../components/DataCard";
import { PageHeader } from "../components/PageHeader";

const rows = [
  {
    type: "string",
    purpose: "Short text.",
    form: "Text input.",
    stored: "JSON string",
    example: '"debian"',
  },
  {
    type: "text",
    purpose: "Longer text.",
    form: "Textarea.",
    stored: "JSON string",
    example: '"Long description"',
  },
  {
    type: "integer",
    purpose: "Whole number.",
    form: "Number input.",
    stored: "JSON number",
    example: "110",
  },
  {
    type: "number",
    purpose: "Decimal or whole number.",
    form: "Number input.",
    stored: "JSON number",
    example: "0.75",
  },
  {
    type: "boolean",
    purpose: "True/false value.",
    form: "True/False selector.",
    stored: "JSON boolean",
    example: "true",
  },
  {
    type: "enum",
    purpose: "One value from a fixed list.",
    form: "Select.",
    stored: "JSON string",
    example: '"router"',
  },
  {
    type: "reference",
    purpose: "Link to one Resource of another Resource Type.",
    form: "Select target Resource.",
    stored: "Target Resource ID string",
    example: '"10.10.10.20"',
  },
  {
    type: "reference_array",
    purpose: "Link to multiple Resources of another Resource Type.",
    form: "Multi-select.",
    stored: "Array of target Resource ID strings",
    example: '["mysql", "dns"]',
  },
  {
    type: "array",
    purpose: "Generic list.",
    form: "JSON array input.",
    stored: "JSON array",
    example: '["80/tcp", "443/tcp"]',
  },
  {
    type: "json",
    purpose: "Structured object.",
    form: "JSON object input.",
    stored: "JSON object",
    example: '{"cpu": 2, "memory": "4GiB"}',
  },
  {
    type: "ip",
    purpose: "IP address.",
    form: "Text input.",
    stored: "JSON string",
    example: '"10.10.10.20"',
  },
  {
    type: "cidr",
    purpose: "Network prefix.",
    form: "Text input.",
    stored: "JSON string",
    example: '"10.10.10.0/24"',
  },
  {
    type: "url",
    purpose: "URL.",
    form: "URL input.",
    stored: "JSON string",
    example: '"https://zabbix.example.internal"',
  },
];

export function FieldTypesGuidePage() {
  return (
    <section className="space-y-5">
      <PageHeader title="Administration / Field Types" />
      <DataCard title="Field Types">
        <div className="space-y-3">
          {rows.map((row) => (
            <div
              key={row.type}
              className="rounded-lg border border-gray-200 p-3 text-sm"
            >
              <p className="font-semibold text-gray-900">{row.type}</p>
              <p className="text-gray-700">Purpose: {row.purpose}</p>
              <p className="text-gray-700">Resource form: {row.form}</p>
              <p className="text-gray-700">Stored value: {row.stored}</p>
              <p className="font-mono text-xs text-gray-600">
                Example: {row.example}
              </p>
            </div>
          ))}
        </div>
      </DataCard>

      <DataCard title="Recommendations">
        <ul className="list-disc space-y-1 pl-5 text-sm text-gray-700">
          <li>
            Use reference instead of plain string when the value points to
            another Resource.
          </li>
          <li>Use enum when allowed values are small and stable.</li>
          <li>Use json only when no simpler field type fits.</li>
          <li>Use array for simple lists.</li>
          <li>Use text for descriptions.</li>
          <li>Use ip and cidr instead of string for network values.</li>
        </ul>
      </DataCard>
    </section>
  );
}
