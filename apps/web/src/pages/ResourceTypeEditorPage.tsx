import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { api } from '../api/client'
import { ActionButton } from '../components/Action'
import { Button } from '../components/Button'
import { DataCard } from '../components/DataCard'
import { ErrorBanner } from '../components/ErrorBanner'
import { PageHeader } from '../components/PageHeader'
import { SelectInput } from '../components/SelectInput'
import { TextInput } from '../components/TextInput'
import { TextareaInput } from '../components/TextareaInput'
import { defaultResourceType, type FieldDef, type ResourceType } from '../types'

function parseJsonArray(text: string): unknown[] {
  const trimmed = text.trim()
  if (!trimmed) return []
  return JSON.parse(trimmed)
}

const fieldTypes: FieldDef['type'][] = ['string', 'text', 'integer', 'number', 'boolean', 'enum', 'array', 'json', 'reference', 'reference_array', 'ip', 'cidr', 'url']

export function ResourceTypeEditorPage({ mode }: { mode: 'create' | 'edit' }) {
  const { type = '' } = useParams()
  const navigate = useNavigate()
  const [value, setValue] = useState<ResourceType>(defaultResourceType())
  const [error, setError] = useState<string | null>(null)
  const [advanced, setAdvanced] = useState({ form_layout: '[]', detail_sections: '[]', validation_rules: '[]' })

  useEffect(() => {
    if (mode === 'edit' && type) {
      api
        .getResourceType(type)
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

  const removeField = (idx: number) => {
    const fields = value.fields.filter((_, i) => i !== idx)
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
    <section className="space-y-5">
      <PageHeader title={mode === 'create' ? 'Administration / Resource Types / Create Resource Type' : `Administration / Resource Types / ${type} / Edit schema`} />
      <ErrorBanner message={error} />
      <DataCard title="General">
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <label className="block text-sm font-medium text-gray-700">ID<TextInput value={value.id} disabled={mode === 'edit'} onChange={(e) => setValue({ ...value, id: e.target.value })} /></label>
          <label className="block text-sm font-medium text-gray-700">Title<TextInput value={value.title} onChange={(e) => setValue({ ...value, title: e.target.value })} /></label>
          <label className="block text-sm font-medium text-gray-700">Group<TextInput value={value.group} onChange={(e) => setValue({ ...value, group: e.target.value })} /></label>
          <label className="block text-sm font-medium text-gray-700 md:col-span-2">Description<TextareaInput rows={3} value={value.description} onChange={(e) => setValue({ ...value, description: e.target.value })} /></label>
        </div>
      </DataCard>
      <DataCard title="Fields" actions={<Button variant="secondary" onClick={() => setValue({ ...value, fields: [...value.fields, { name: '', label: '', type: 'string', enum_values: [] }] })}>Add field</Button>}>
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200 text-sm">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-3 py-2 text-left">Name</th>
                <th className="px-3 py-2 text-left">Label</th>
                <th className="px-3 py-2 text-left">Type</th>
                <th className="px-3 py-2 text-left">Enum values</th>
                <th className="px-3 py-2 text-left">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {value.fields.map((f, i) => (
                <tr key={`${f.name}-${i}`}>
                  <td className="px-3 py-2"><TextInput value={f.name} onChange={(e) => upsertField(i, { ...f, name: e.target.value })} /></td>
                  <td className="px-3 py-2"><TextInput value={f.label} onChange={(e) => upsertField(i, { ...f, label: e.target.value })} /></td>
                  <td className="px-3 py-2">
                    <SelectInput value={f.type} onChange={(e) => upsertField(i, { ...f, type: e.target.value as FieldDef['type'] })}>
                      {fieldTypes.map((t) => <option key={t} value={t}>{t}</option>)}
                    </SelectInput>
                  </td>
                  <td className="px-3 py-2"><TextInput value={f.enum_values.join(',')} onChange={(e) => upsertField(i, { ...f, enum_values: e.target.value.split(',').map((s) => s.trim()).filter(Boolean) })} /></td>
                  <td className="px-3 py-2"><ActionButton tone="danger" onClick={() => removeField(i)}>Remove</ActionButton></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </DataCard>
      <DataCard title="Required and list columns">
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <label className="block text-sm font-medium text-gray-700">Required fields (comma separated)<TextInput value={value.required_fields.join(',')} onChange={(e) => setValue({ ...value, required_fields: e.target.value.split(',').map((s) => s.trim()) })} /></label>
          <label className="block text-sm font-medium text-gray-700">List columns (comma separated)<TextInput value={value.list_columns.join(',')} onChange={(e) => setValue({ ...value, list_columns: e.target.value.split(',').map((s) => s.trim()) })} /></label>
        </div>
      </DataCard>
      <DataCard title="Advanced JSON">
        <div className="space-y-4">
          <label className="block text-sm font-medium text-gray-700">form_layout<TextareaInput rows={6} value={advanced.form_layout} onChange={(e) => setAdvanced({ ...advanced, form_layout: e.target.value })} /></label>
          <label className="block text-sm font-medium text-gray-700">detail_sections<TextareaInput rows={6} value={advanced.detail_sections} onChange={(e) => setAdvanced({ ...advanced, detail_sections: e.target.value })} /></label>
          <label className="block text-sm font-medium text-gray-700">validation_rules<TextareaInput rows={6} value={advanced.validation_rules} onChange={(e) => setAdvanced({ ...advanced, validation_rules: e.target.value })} /></label>
        </div>
      </DataCard>
      <div className="flex justify-end">
        <Button onClick={save}>Save</Button>
      </div>
    </section>
  )
}
