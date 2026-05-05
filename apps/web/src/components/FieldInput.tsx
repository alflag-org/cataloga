import type { FieldDef } from '../types'

type Props = {
  field: FieldDef
  value: unknown
  onChange: (value: unknown) => void
}

function asText(value: unknown): string {
  if (value == null) return ''
  if (typeof value === 'string') return value
  try {
    return JSON.stringify(value)
  } catch {
    return String(value)
  }
}

export function FieldInput({ field, value, onChange }: Props) {
  const base = `field-${field.name}`
  if (field.type === 'text' || field.type === 'json' || field.type === 'array' || field.type === 'reference_array') {
    return (
      <textarea
        id={base}
        value={asText(value)}
        onChange={(e) => onChange(e.target.value)}
        rows={4}
      />
    )
  }
  if (field.type === 'boolean') {
    return (
      <input
        id={base}
        type="checkbox"
        checked={Boolean(value)}
        onChange={(e) => onChange(e.target.checked)}
      />
    )
  }
  if (field.type === 'enum') {
    return (
      <select id={base} value={asText(value)} onChange={(e) => onChange(e.target.value)}>
        <option value="">Select</option>
        {field.enum_values.map((opt) => (
          <option key={opt} value={opt}>
            {opt}
          </option>
        ))}
      </select>
    )
  }
  if (field.type === 'integer' || field.type === 'number') {
    return <input id={base} type="number" value={asText(value)} onChange={(e) => onChange(e.target.value)} />
  }
  if (field.type === 'url') {
    return <input id={base} type="url" value={asText(value)} onChange={(e) => onChange(e.target.value)} />
  }
  return <input id={base} type="text" value={asText(value)} onChange={(e) => onChange(e.target.value)} />
}
