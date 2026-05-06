import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { api } from '../api/client'
import { LinkButton } from '../components/Button'
import { DataCard } from '../components/DataCard'
import { ErrorBanner } from '../components/ErrorBanner'
import { PageHeader } from '../components/PageHeader'
import { compactValue, type Resource } from '../types'

export function ResourceDetailPage() {
  const { type = '', id = '' } = useParams()
  const [resource, setResource] = useState<Resource | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    api.getResource(type, id).then(setResource).catch((e) => setError(e.message))
  }, [type, id])

  return (
    <section className="space-y-5">
      <PageHeader title={`Resource: ${type}/${id}`} actions={<LinkButton to={`/resource-types/${type}/${id}/edit`}>Edit</LinkButton>} />
      <ErrorBanner message={error} />
      {resource ? (
        <>
          <DataCard title="Metadata">
            <pre className="overflow-x-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-700">{compactValue(resource.metadata)}</pre>
          </DataCard>
          <DataCard title="Spec">
            <pre className="overflow-x-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-700">{compactValue(resource.spec)}</pre>
          </DataCard>
          <DataCard title="custom_fields">
            <pre className="overflow-x-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-700">{compactValue(resource.custom_fields)}</pre>
          </DataCard>
          <DataCard title="dependencies">
            <pre className="overflow-x-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-700">{compactValue(resource.dependencies)}</pre>
          </DataCard>
          <DataCard title="Raw JSON">
            <pre className="overflow-x-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">{JSON.stringify(resource, null, 2)}</pre>
          </DataCard>
        </>
      ) : null}
    </section>
  )
}
