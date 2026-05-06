import { useEffect, useMemo, useState } from 'react'
import { api } from '../api/client'
import { ErrorBanner } from '../components/ErrorBanner'
import { PageHeader } from '../components/PageHeader'
import { ResourceTypeCard } from '../components/ResourceTypeCard'
import { TextInput } from '../components/TextInput'
import type { ResourceType } from '../types'

export function ResourcesIndexPage() {
  const [types, setTypes] = useState<ResourceType[]>([])
  const [counts, setCounts] = useState<Record<string, number>>({})
  const [query, setQuery] = useState('')
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    ;(async () => {
      try {
        const rt = await api.listResourceTypes()
        setTypes(rt)
        const entries = await Promise.all(rt.map(async (t) => [t.id, (await api.listResources(t.id)).length] as const))
        setCounts(Object.fromEntries(entries))
      } catch (e) {
        setError(e instanceof Error ? e.message : String(e))
      }
    })()
  }, [])

  const grouped = useMemo(() => {
    const q = query.trim().toLowerCase()
    const filtered = types.filter((t) => {
      if (!q) return true
      return [t.id, t.title, t.group].join(' ').toLowerCase().includes(q)
    })

    return filtered.reduce<Record<string, ResourceType[]>>((acc, t) => {
      const group = t.group?.trim() || 'Other'
      acc[group] = acc[group] ?? []
      acc[group].push(t)
      return acc
    }, {})
  }, [types, query])

  return (
    <section className="space-y-5">
      <PageHeader title="Resources" />
      <ErrorBanner message={error} />
      <div className="space-y-2">
        <p className="text-sm font-medium text-gray-700">Search resource types</p>
        <TextInput value={query} onChange={(e) => setQuery(e.target.value)} />
      </div>

      {Object.entries(grouped).map(([group, items]) => (
        <div key={group} className="space-y-3">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-700">{group}</h2>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {items.map((t) => (
              <ResourceTypeCard key={t.id} title={t.title || t.id} typeId={t.id} count={counts[t.id] ?? 0} to={`/resources/${t.id}`} />
            ))}
          </div>
        </div>
      ))}
    </section>
  )
}
