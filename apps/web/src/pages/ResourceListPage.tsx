import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { api } from '../api/client'
import { LinkButton } from '../components/Button'
import { DataCard } from '../components/DataCard'
import { EmptyState } from '../components/EmptyState'
import { ErrorBanner } from '../components/ErrorBanner'
import { PageHeader } from '../components/PageHeader'
import { ResourceListTable } from '../components/ResourceListTable'
import type { Resource, ResourceType } from '../types'

export function ResourceListPage() {
  const { type = '' } = useParams()
  const [rt, setRt] = useState<ResourceType | null>(null)
  const [rows, setRows] = useState<Resource[]>([])
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

  return (
    <section className="space-y-5">
      <PageHeader
        title={`Resources: ${type}`}
        actions={<LinkButton to={`/resource-types/${type}/new`}>Create Resource</LinkButton>}
      />
      <ErrorBanner message={error} />
      <DataCard>
        {rows.length ? (
          <ResourceListTable type={type} columns={cols} rows={rows} />
        ) : (
          <EmptyState
            title="No resources"
            description="Create the first resource for this resource type."
            action={<LinkButton to={`/resource-types/${type}/new`}>Create Resource</LinkButton>}
          />
        )}
      </DataCard>
    </section>
  )
}
