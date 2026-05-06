import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api/client'
import { LinkButton } from '../components/Button'
import { DataCard } from '../components/DataCard'
import { ErrorBanner } from '../components/ErrorBanner'
import { PageHeader } from '../components/PageHeader'
import type { ResourceType } from '../types'

export function ResourceTypeListPage() {
  const [items, setItems] = useState<ResourceType[]>([])
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    api.listResourceTypes().then(setItems).catch((e) => setError(e.message))
  }, [])

  return (
    <section className="space-y-5">
      <PageHeader title="Resource Types" actions={<LinkButton to="/resource-types/new">Create Resource Type</LinkButton>} />
      <ErrorBanner message={error} />
      <DataCard>
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200 text-sm">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-3 py-2 text-left font-semibold text-gray-600">Title</th>
                <th className="px-3 py-2 text-left font-semibold text-gray-600">ID</th>
                <th className="px-3 py-2 text-left font-semibold text-gray-600">Group</th>
                <th className="px-3 py-2 text-left font-semibold text-gray-600">Fields</th>
                <th className="px-3 py-2 text-left font-semibold text-gray-600">List columns</th>
                <th className="px-3 py-2 text-left font-semibold text-gray-600">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100 bg-white">
              {items.map((t) => (
                <tr key={t.id} className="hover:bg-gray-50">
                  <td className="px-3 py-2 font-medium text-gray-800">{t.title || t.id}</td>
                  <td className="px-3 py-2 text-gray-700">{t.id}</td>
                  <td className="px-3 py-2 text-gray-700">{t.group || '-'}</td>
                  <td className="px-3 py-2 text-gray-700">{t.fields.length}</td>
                  <td className="px-3 py-2 text-gray-700">{t.list_columns.join(', ') || '-'}</td>
                  <td className="px-3 py-2">
                    <div className="flex items-center gap-3">
                      <Link className="text-blue-600 hover:text-blue-700" to={`/resource-types/${t.id}`}>
                        Open
                      </Link>
                      <Link className="text-gray-700 hover:text-gray-900" to={`/resource-types/${t.id}/edit`}>
                        Edit
                      </Link>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </DataCard>
    </section>
  )
}
