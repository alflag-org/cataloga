import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api } from '../api/client'
import { Button, LinkButton } from '../components/Button'
import { DataCard } from '../components/DataCard'
import { ErrorBanner } from '../components/ErrorBanner'
import { PageHeader } from '../components/PageHeader'
import { compactValue, type Resource } from '../types'
import type { ResourceReferences } from '../types'

export function ResourceDetailPage() {
  const { type = '', id = '' } = useParams()
  const navigate = useNavigate()
  const [resource, setResource] = useState<Resource | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [references, setReferences] = useState<ResourceReferences | null>(null)

  useEffect(() => {
    api.getResource(type, id).then(setResource).catch((e) => setError(e.message))
    api.getResourceReferences(type, id).then(setReferences).catch((e) => setError(e.message))
  }, [type, id])

  return (
    <section className="space-y-5">
      <PageHeader
        title={`Resource: ${type}/${id}`}
        actions={
          <div className="flex gap-2">
            <LinkButton to={`/resource-types/${type}/${id}/edit`}>Edit</LinkButton>
            <Button
              variant="danger"
              onClick={async () => {
                if (!window.confirm(`Delete Resource '${type}/${id}'?`)) return
                try {
                  await api.deleteResource(type, id)
                  navigate(`/resource-types/${type}`)
                } catch (e) {
                  setError(e instanceof Error ? e.message : String(e))
                }
              }}
            >
              Delete
            </Button>
          </div>
        }
      />
      <ErrorBanner message={error} />
      {resource ? (
        <>
          <DataCard title="Metadata">
            <pre className="overflow-x-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-700">{compactValue(resource.metadata)}</pre>
          </DataCard>
          <DataCard title="Spec">
            <pre className="overflow-x-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-700">{compactValue(resource.spec)}</pre>
          </DataCard>
          <DataCard title="Custom fields">
            <pre className="overflow-x-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-700">{compactValue(resource.custom_fields)}</pre>
          </DataCard>
          <DataCard title="Dependencies">
            <pre className="overflow-x-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-700">{compactValue(resource.dependencies)}</pre>
          </DataCard>
          <DataCard title="Referenced resources">
            <div className="space-y-2">
              {references && references.outgoing.length > 0 ? (
                references.outgoing.map((item, idx) => (
                  <div key={`${item.field}-${item.resource_type}-${item.resource_id}-${idx}`} className="rounded border border-gray-200 px-3 py-2 text-sm">
                    <p>field: {item.field}</p>
                    <p>type: {item.resource_type}</p>
                    <p>
                      target:{' '}
                      <Link className="text-blue-700 underline" to={`/resource-types/${item.resource_type}/${item.resource_id}`}>
                        {item.name} ({item.resource_id})
                      </Link>
                    </p>
                  </div>
                ))
              ) : (
                <p className="text-sm text-gray-600">No referenced resources.</p>
              )}
            </div>
          </DataCard>
          <DataCard title="Used by">
            <div className="space-y-2">
              {references && references.incoming.length > 0 ? (
                references.incoming.map((item, idx) => (
                  <div key={`${item.field}-${item.resource_type}-${item.resource_id}-${idx}`} className="rounded border border-gray-200 px-3 py-2 text-sm">
                    <p>field: {item.field}</p>
                    <p>type: {item.resource_type}</p>
                    <p>
                      source:{' '}
                      <Link className="text-blue-700 underline" to={`/resource-types/${item.resource_type}/${item.resource_id}`}>
                        {item.name} ({item.resource_id})
                      </Link>
                    </p>
                  </div>
                ))
              ) : (
                <p className="text-sm text-gray-600">No resources reference this resource.</p>
              )}
            </div>
          </DataCard>
          <DataCard title="Raw JSON">
            <pre className="overflow-x-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">{JSON.stringify(resource, null, 2)}</pre>
          </DataCard>
        </>
      ) : null}
    </section>
  )
}
