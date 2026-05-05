import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { api } from '../api/client'
import { ErrorBanner } from '../components/ErrorBanner'
import { PageHeader } from '../components/PageHeader'
import { defaultResourceType, type FieldDef, type ResourceType } from '../types'

function parseJsonArray(text: string): unknown[] {
  const trimmed = text.trim()
  if (!trimmed) return []
  return JSON.parse(trimmed)
}

export function ResourceTypeEditorPage({ mode }: { mode: 'create' | 'edit' }) {
  const { type = '' } = useParams()
  const navigate = useNavigate()
  const [value, setValue] = useState<ResourceType>(defaultResourceType())
  const [error, setError] = useState<string | null>(null)
  const [advanced, setAdvanced] = useState({ form_layout: '[]', detail_sections: '[]', validation_rules: '[]' })

  useEffect(() => {
    if (mode === 'edit' && type) {
      api.getResourceType(type)
        .then((rt) => {
          setValue(rt)
          setAdvanced({
            form_layout: JSON.stringify(rt.form_layout ?? [], null, 2),
            detail_sections: JSON.stringify(rt.detail_sections ?? [], null, 2),
            validation_rules: JSON.stringify(rt.validation_rules ?? [], null, 2)
          })
        })
        .catch((e) => setError(e.message))
    }
  }, [mode, type])

  const upsertField = (idx: number, next: FieldDef) => {
    const fields = [...value.fields]
    fields[idx] = next
    setValue({ ...value, fields })
  }

  const save = async () => {
    try {
      setError(null)
      const payload: ResourceType = {
        ...value,
        required_fields: value.required_fields.filter(Boolean),
        list_columns: value.list_columns.filter(Boolean),
        form_layout: parseJsonArray(advanced.form_layout) as ResourceType['form_layout'],
        detail_sections: parseJsonArray(advanced.detail_sections) as ResourceType['detail_sections'],
        validation_rules: parseJsonArray(advanced.validation_rules) as ResourceType['validation_rules']
      }
      if (mode === 'create') await api.upsertResourceType(payload)
      else await api.updateResourceType(type, payload)
      navigate('/resource-types')
    } catch (e) {
      setError(e instanceof Error ? e.message : String(e))
    }
  }

  return (
    <section>
      <PageHeader title={mode === 'create' ? 'Create Resource Type' : `Edit Resource Type: ${type}`} />
      <ErrorBanner message={error} />
      <label>ID <input value={value.id} disabled={mode === 'edit'} onChange={(e) => setValue({ ...value, id: e.target.value })} /></label>
      <label>Title <input value={value.title} onChange={(e) => setValue({ ...value, title: e.target.value })} /></label>
      <label>Group <input value={value.group} onChange={(e) => setValue({ ...value, group: e.target.value })} /></label>
      <label>Description <textarea value={value.description} onChange={(e) => setValue({ ...value, description: e.target.value })} /></label>
      <h2>Fields</h2>
      {value.fields.map((f, i) => (
        <div key={`${f.name}-${i}`} className="field-row">
          <input placeholder="name" value={f.name} onChange={(e) => upsertField(i, { ...f, name: e.target.value })} />
          <input placeholder="label" value={f.label} onChange={(e) => upsertField(i, { ...f, label: e.target.value })} />
          <select value={f.type} onChange={(e) => upsertField(i, { ...f, type: e.target.value as FieldDef['type'] })}>
            {['string','text','integer','number','boolean','enum','array','json','reference','reference_array','ip','cidr','url'].map((t) => <option key={t} value={t}>{t}</option>)}
          </select>
          <input placeholder="enum values (comma separated)" value={f.enum_values.join(',')} onChange={(e) => upsertField(i, { ...f, enum_values: e.target.value.split(',').map((s) => s.trim()).filter(Boolean) })} />
        </div>
      ))}
      <button onClick={() => setValue({ ...value, fields: [...value.fields, { name: '', label: '', type: 'string', enum_values: [] }] })}>Add field</button>
      <label>Required fields (comma separated) <input value={value.required_fields.join(',')} onChange={(e) => setValue({ ...value, required_fields: e.target.value.split(',').map((s) => s.trim()) })} /></label>
      <label>List columns (comma separated) <input value={value.list_columns.join(',')} onChange={(e) => setValue({ ...value, list_columns: e.target.value.split(',').map((s) => s.trim()) })} /></label>
      <details>
        <summary>Advanced JSON</summary>
        <label>form_layout <textarea rows={6} value={advanced.form_layout} onChange={(e) => setAdvanced({ ...advanced, form_layout: e.target.value })} /></label>
        <label>detail_sections <textarea rows={6} value={advanced.detail_sections} onChange={(e) => setAdvanced({ ...advanced, detail_sections: e.target.value })} /></label>
        <label>validation_rules <textarea rows={6} value={advanced.validation_rules} onChange={(e) => setAdvanced({ ...advanced, validation_rules: e.target.value })} /></label>
      </details>
      <button onClick={save}>Save</button>
    </section>
  )
}
