import { useMemo, useState } from 'react'
import type { Resource, ResourceType } from '../types'
import { FieldInput } from './FieldInput'

type Props = {
  resourceType: ResourceType
  initial: Resource
  mode: 'create' | 'edit'
  onSubmit: (resource: Resource) => Promise<void>
}

function parseFieldValue(type: string, raw: unknown): unknown {
  if (raw == null) return raw
  if (type === 'integer') {
    const n = Number(raw)
    return Number.isFinite(n) ? Math.trunc(n) : 0
  }
  if (type === 'number') {
    const n = Number(raw)
    return Number.isFinite(n) ? n : 0
  }
  if (type === 'boolean') return Boolean(raw)
  if (type === 'array') {
    if (Array.isArray(raw)) return raw
    const s = String(raw).trim()
    if (!s) return []
    return JSON.parse(s)
  }
  if (type === 'json') {
    if (typeof raw === 'object') return raw
    const s = String(raw).trim()
    if (!s) return {}
    return JSON.parse(s)
  }
  if (type === 'reference_array') {
    if (Array.isArray(raw)) return raw.map(String)
    const s = String(raw).trim()
    if (!s) return []
    try {
      return JSON.parse(s)
    } catch {
      return s.split('\n').map((x) => x.trim()).filter(Boolean)
    }
  }
  return String(raw)
}

export function ResourceForm({ resourceType, initial, mode, onSubmit }: Props) {
  const [form, setForm] = useState<Resource>(initial)
  const [error, setError] = useState<string | null>(null)
  const required = useMemo(() => new Set(resourceType.required_fields), [resourceType.required_fields])

  const submit = async () => {
    try {
      setError(null)
      const next: Resource = {
        ...form,
        metadata: { ...form.metadata, type: resourceType.id },
        spec: { ...form.spec }
      }
      for (const field of resourceType.fields) {
        const raw = next.spec[field.name]
        if (raw == null || raw === '') {
          if (required.has(field.name)) throw new Error(`missing required field: ${field.name}`)
          delete next.spec[field.name]
          continue
        }
        next.spec[field.name] = parseFieldValue(field.type, raw)
      }
      await onSubmit(next)
    } catch (e) {
      setError(e instanceof Error ? e.message : String(e))
    }
  }

  return (
    <div>
      {error ? <div className="error-banner">Error: {error}</div> : null}
      <div className="form-grid">
        <label>
          ID
          <input
            value={form.metadata.id}
            disabled={mode === 'edit'}
            onChange={(e) => setForm({ ...form, metadata: { ...form.metadata, id: e.target.value } })}
          />
        </label>
        <label>
          Name
          <input
            value={form.metadata.name}
            onChange={(e) => setForm({ ...form, metadata: { ...form.metadata, name: e.target.value } })}
          />
        </label>
      </div>
      {resourceType.fields.map((field) => (
        <label key={field.name}>
          {field.label || field.name}
          <FieldInput
            field={field}
            value={form.spec[field.name]}
            onChange={(value) =>
              setForm({
                ...form,
                spec: {
                  ...form.spec,
                  [field.name]: value
                }
              })
            }
          />
        </label>
      ))}
      <div>
        <button onClick={submit}>Save</button>
      </div>
    </div>
  )
}
