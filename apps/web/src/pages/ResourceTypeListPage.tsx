import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api/client'
import { ErrorBanner } from '../components/ErrorBanner'
import { PageHeader } from '../components/PageHeader'
import { ResourceTypeNav } from '../components/ResourceTypeNav'
import type { ResourceType } from '../types'

export function ResourceTypeListPage() {
  const [items, setItems] = useState<ResourceType[]>([])
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    api.listResourceTypes().then(setItems).catch((e) => setError(e.message))
  }, [])

  return (
    <section>
      <PageHeader title="Resource Types" actions={<Link to="/resource-types/new">Create</Link>} />
      <ErrorBanner message={error} />
      <ResourceTypeNav types={items} />
      <ul>
        {items.map((t) => (
          <li key={t.id}>
            <Link to={`/resource-types/${t.id}`}>Resources</Link>{' '}
            <Link to={`/resource-types/${t.id}/edit`}>Edit Type</Link>
          </li>
        ))}
      </ul>
    </section>
  )
}
