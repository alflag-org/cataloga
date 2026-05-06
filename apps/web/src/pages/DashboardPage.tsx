import { useEffect, useMemo, useState } from 'react'
import { api } from '../api/client'
import { LinkButton } from '../components/Button'
import { DataCard } from '../components/DataCard'
import { ErrorBanner } from '../components/ErrorBanner'
import { PageHeader } from '../components/PageHeader'
import { StatCard } from '../components/StatCard'
import type { ResourceType } from '../types'

export function DashboardPage() {
  const [types, setTypes] = useState<ResourceType[]>([])
  const [counts, setCounts] = useState<Record<string, number>>({})
  const [health, setHealth] = useState('unknown')
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    ;(async () => {
      try {
        const rt = await api.listResourceTypes()
        setTypes(rt)
        const entries = await Promise.all(rt.map(async (t) => [t.id, (await api.listResources(t.id)).length] as const))
        setCounts(Object.fromEntries(entries))
        const h = await api.health()
        setHealth(h.status)
      } catch (e) {
        setError(e instanceof Error ? e.message : String(e))
      }
    })()
  }, [])

  const totalResources = useMemo(() => Object.values(counts).reduce((acc, x) => acc + x, 0), [counts])

  return (
    <section className="space-y-5">
      <PageHeader title="Dashboard" subtitle="Resource catalog overview" />
      <ErrorBanner message={error} />
      <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
        <StatCard label="Health" value={health} />
        <StatCard label="Resource Types" value={types.length} />
        <StatCard label="Total Resources" value={totalResources} />
      </div>
      <DataCard title="Resources per type">
        <div className="space-y-2">
          {types.map((t) => (
            <div key={t.id} className="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2">
              <span className="text-sm font-medium text-gray-800">{t.title || t.id}</span>
              <span className="text-sm text-gray-600">{counts[t.id] ?? 0}</span>
            </div>
          ))}
        </div>
      </DataCard>
      <DataCard title="Quick actions">
        <div className="flex flex-wrap gap-2">
          <LinkButton to="/resource-types/new">Create Resource Type</LinkButton>
          <LinkButton to="/import" variant="secondary">
            Import YAML
          </LinkButton>
          <LinkButton to="/export" variant="secondary">
            Export YAML
          </LinkButton>
        </div>
      </DataCard>
    </section>
  )
}
