import type { FieldDef } from '../types'
import { SelectInput } from './SelectInput'
import { TextInput } from './TextInput'
import { TextareaInput } from './TextareaInput'

type Props = {
  field: FieldDef
  value: unknown
  onChange: (value: unknown) => void
}

function asText(value: unknown): string {
  if (value == null) return ''
  if (typeof value === 'string') return value
  try {
    return JSON.stringify(value, null, 2)
  } catch {
    return String(value)
  }
}

export function FieldInput({ field, value, onChange }: Props) {
  const base = `field-${field.name}`
  if (field.type === 'text' || field.type === 'json' || field.type === 'array' || field.type === 'reference_array') {
    return <TextareaInput id={base} value={asText(value)} onChange={(e) => onChange(e.target.value)} rows={4} />
  }
  if (field.type === 'boolean') {
    return (
      <label className="inline-flex items-center gap-2 text-sm text-gray-700">
        <input id={base} type="checkbox" className="rounded border-gray-300 text-blue-600" checked={Boolean(value)} onChange={(e) => onChange(e.target.checked)} />
        Enabled
      </label>
    )
  }
  if (field.type === 'enum') {
    return (
      <SelectInput id={base} value={asText(value)} onChange={(e) => onChange(e.target.value)}>
        <option value="">Select</option>
        {field.enum_values.map((opt) => (
          <option key={opt} value={opt}>
            {opt}
          </option>
        ))}
      </SelectInput>
    )
  }
  if (field.type === 'integer' || field.type === 'number') {
    return <TextInput id={base} type="number" value={asText(value)} onChange={(e) => onChange(e.target.value)} />
  }
  if (field.type === 'url') {
    return <TextInput id={base} type="url" value={asText(value)} onChange={(e) => onChange(e.target.value)} />
  }
  return <TextInput id={base} type="text" value={asText(value)} onChange={(e) => onChange(e.target.value)} />
}
