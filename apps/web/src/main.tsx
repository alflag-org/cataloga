import React, { useEffect, useMemo, useState } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter, Link, Route, Routes, useParams } from 'react-router-dom'

type FieldType =
  | 'string'
  | 'text'
  | 'integer'
  | 'number'
  | 'boolean'
  | 'enum'
  | 'array'
  | 'json'
  | 'reference'
  | 'reference_array'
  | 'ip'
  | 'cidr'
  | 'url'

type FieldDef = {
  name: string
  label: string
  type: FieldType
  enum_values: string[]
}

type ResourceType = {
  id: string
  title: string
  list_columns: string[]
  fields: FieldDef[]
}

type Resource = {
  metadata: {
    id: string
    type: string
    name: string
  }
  spec: Record<string, unknown>
}

const API = '/api'

function useResourceTypes() {
  const [types, setTypes] = useState<ResourceType[]>([])

  useEffect(() => {
    fetch(`${API}/resource-types`)
      .then((r) => r.json())
      .then((data) => setTypes(Array.isArray(data) ? data : []))
      .catch(() => setTypes([]))
  }, [])

  return types
}

function Dashboard() {
  return <main><h1>Dashboard</h1><p>Schema-driven infrastructure catalog.</p></main>
}

function ResourceTypeList() {
  const types = useResourceTypes()
  return (
    <main>
      <h1>Resource Types</h1>
      <ul>
        {types.map((t) => (
          <li key={t.id}><Link to={`/resource-types/${t.id}`}>{t.title} ({t.id})</Link></li>
        ))}
      </ul>
    </main>
  )
}

function ResourceListByType() {
  const { type = '' } = useParams()
  const types = useResourceTypes()
  const rt = useMemo(() => types.find((x) => x.id === type), [types, type])
  const [rows, setRows] = useState<Resource[]>([])

  useEffect(() => {
    if (!type) return
    fetch(`${API}/resources/${type}`)
      .then((r) => r.json())
      .then((data) => setRows(Array.isArray(data) ? data : []))
      .catch(() => setRows([]))
  }, [type])

  const columns = rt?.list_columns?.length ? rt.list_columns : ['metadata.name']

  return (
    <main>
      <h1>Resources: {type}</h1>
      <p><Link to={`/resource-types/${type}/new`}>Create Resource</Link></p>
      <table>
        <thead>
          <tr>{columns.map((c) => <th key={c}>{c}</th>)}</tr>
        </thead>
        <tbody>
          {rows.map((r) => (
            <tr key={r.metadata.id}>
              {columns.map((c) => <td key={c}>{readPath(r, c)}</td>)}
            </tr>
          ))}
        </tbody>
      </table>
    </main>
  )
}

function ResourceCreateEdit() {
  const { type = '' } = useParams()
  const types = useResourceTypes()
  const rt = useMemo(() => types.find((x) => x.id === type), [types, type])
  const [name, setName] = useState('')
  const [id, setId] = useState('')
  const [spec, setSpec] = useState<Record<string, string>>({})

  if (!rt) return <main><h1>Create Resource</h1><p>Unknown type</p></main>

  const submit = async () => {
    const payload = {
      api_version: 'cataloga.io/v1',
      kind: 'Resource',
      metadata: { id, type, name, tags: {} },
      spec,
      custom_fields: {},
      dependencies: {}
    }

    await fetch(`${API}/resources/${type}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    window.location.href = `/resource-types/${type}`
  }

  return (
    <main>
      <h1>Create Resource: {rt.title}</h1>
      <label>id <input value={id} onChange={(e) => setId(e.target.value)} /></label><br />
      <label>name <input value={name} onChange={(e) => setName(e.target.value)} /></label>
      {rt.fields.map((f) => (
        <div key={f.name}>
          <label>
            {f.label}
            <input
              value={spec[f.name] ?? ''}
              onChange={(e) => setSpec({ ...spec, [f.name]: e.target.value })}
            />
          </label>
        </div>
      ))}
      <button onClick={submit}>Save</button>
    </main>
  )
}

function ImportPage() {
  const [yaml, setYaml] = useState('')
  return (
    <main>
      <h1>Import</h1>
      <textarea rows={16} cols={100} value={yaml} onChange={(e) => setYaml(e.target.value)} />
      <div>
        <button onClick={async () => {
          await fetch(`${API}/import`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ yaml })
          })
        }}>Import</button>
      </div>
    </main>
  )
}

function ExportPage() {
  const [yaml, setYaml] = useState('')

  return (
    <main>
      <h1>Export</h1>
      <button onClick={async () => setYaml(await (await fetch(`${API}/export`)).text())}>Export</button>
      <pre>{yaml}</pre>
    </main>
  )
}

function ResourceDetail() {
  const { type = '', id = '' } = useParams()
  return <main><h1>Resource detail</h1><p>{type}/{id}</p></main>
}

function App() {
  return (
    <BrowserRouter>
      <nav>
        <Link to="/">Dashboard</Link> |{' '}
        <Link to="/resource-types">Resource Types</Link> |{' '}
        <Link to="/import">Import</Link> |{' '}
        <Link to="/export">Export</Link>
      </nav>
      <Routes>
        <Route path="/" element={<Dashboard />} />
        <Route path="/resource-types" element={<ResourceTypeList />} />
        <Route path="/resource-types/:type" element={<ResourceListByType />} />
        <Route path="/resource-types/:type/new" element={<ResourceCreateEdit />} />
        <Route path="/resource-types/:type/:id" element={<ResourceDetail />} />
        <Route path="/resource-types/:type/:id/edit" element={<ResourceCreateEdit />} />
        <Route path="/import" element={<ImportPage />} />
        <Route path="/export" element={<ExportPage />} />
      </Routes>
    </BrowserRouter>
  )
}

function readPath(row: Resource, path: string): string {
  if (path === 'metadata.name') return row.metadata.name
  if (path.startsWith('spec.')) {
    const key = path.slice('spec.'.length)
    const v = row.spec[key]
    return v == null ? '' : String(v)
  }
  return ''
}

createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
)
