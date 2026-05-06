import { useEffect, useState } from 'react'
import { LinkButton } from '../components/Button'
import { DataCard } from '../components/DataCard'
import { EmptyState } from '../components/EmptyState'
import { ErrorBanner } from '../components/ErrorBanner'
import { PageHeader } from '../components/PageHeader'
import { ResourceListTable } from '../components/ResourceListTable'
import { SelectInput } from '../components/SelectInput'
import { TextInput } from '../components/TextInput'
import { api } from '../api/client'
import { readPath, type Resource, type ResourceType } from '../types'
import { useParams } from 'react-router-dom'

export function ResourceListPage() {
  const { type = '' } = useParams()
  const [rt, setRt] = useState<ResourceType | null>(null)
  const [rows, setRows] = useState<Resource[]>([])
  const [query, setQuery] = useState('')
  const [sortBy, setSortBy] = useState('metadata.name')
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('asc')
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    ;(async () => {
      try {
        const [resourceType, resources] = await Promise.all([api.getResourceType(type), api.listResources(type)])
        setRt(resourceType)
        setRows(resources)
      } catch (e) {
        setError(e instanceof Error ? e.message : String(e))
      }
    })()
  }, [type])

  const cols = rt?.list_columns?.length ? rt.list_columns : ['metadata.name']
  const filtered = rows.filter((r) => {
    const q = query.trim().toLowerCase()
    if (!q) return true
    return [r.metadata.id, r.metadata.name, r.metadata.type, JSON.stringify(r.metadata.tags), JSON.stringify(r.spec)].join(' ').toLowerCase().includes(q)
  })
  const sorted = [...filtered].sort((a, b) => {
    const av = readPath(a, sortBy).toLowerCase()
    const bv = readPath(b, sortBy).toLowerCase()
    if (av === bv) return 0
    const order = av < bv ? -1 : 1
    return sortDir === 'asc' ? order : -order
  })

  return (
    <section className="space-y-5">
      <PageHeader title={`Resources / ${rt?.title || type}`} actions={<LinkButton to={`/resources/${type}/new`}>Create Resource</LinkButton>} />
      <ErrorBanner message={error} />
      <DataCard>
        <div className="mb-4 grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto_auto] md:items-end">
          <label className="text-sm text-gray-700">
            Search
            <TextInput value={query} onChange={(e) => setQuery(e.target.value)} />
          </label>
          <label className="text-sm text-gray-700">
            Sort by
            <SelectInput value={sortBy} onChange={(e) => setSortBy(e.target.value)}>
              {cols.map((c) => (
                <option key={c} value={c}>{c}</option>
              ))}
            </SelectInput>
          </label>
          <label className="text-sm text-gray-700">
            Order
            <SelectInput value={sortDir} onChange={(e) => setSortDir(e.target.value as 'asc' | 'desc')}>
              <option value="asc">asc</option>
              <option value="desc">desc</option>
            </SelectInput>
          </label>
        </div>
        {sorted.length ? (
          <ResourceListTable
            type={type}
            columns={cols}
            rows={sorted}
            sortBy={sortBy}
            sortDir={sortDir}
            onSort={(column) => {
              if (sortBy === column) setSortDir((prev) => (prev === 'asc' ? 'desc' : 'asc'))
              else {
                setSortBy(column)
                setSortDir('asc')
              }
            }}
            onDelete={async (resourceId) => {
              if (!window.confirm(`Delete Resource '${type}/${resourceId}'?`)) return
              try {
                await api.deleteResource(type, resourceId)
                setRows((prev) => prev.filter((r) => r.metadata.id !== resourceId))
              } catch (e) {
                setError(e instanceof Error ? e.message : String(e))
              }
            }}
          />
        ) : (
          <EmptyState title="No resources" description="Create Resource" action={<LinkButton to={`/resources/${type}/new`}>Create Resource</LinkButton>} />
        )}
      </DataCard>
    </section>
  )
}
