import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api/client'
import { ErrorBanner } from '../components/ErrorBanner'
import { PageHeader } from '../components/PageHeader'
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

  return (
    <section>
      <PageHeader title="Dashboard" subtitle="Catalog workspace overview" />
      <ErrorBanner message={error} />
      <ul>
        <li>Resource Types: {types.length}</li>
        <li>Health: {health}</li>
      </ul>
      <h2>Resources per type</h2>
      <ul>
        {types.map((t) => (
          <li key={t.id}>{t.id}: {counts[t.id] ?? 0}</li>
        ))}
      </ul>
      <p>
        <Link to="/resource-types">Resource Types</Link> | <Link to="/import">Import</Link> |{' '}
        <Link to="/export">Export</Link> | <Link to="/settings">Settings</Link>
      </p>
    </section>
  )
}
