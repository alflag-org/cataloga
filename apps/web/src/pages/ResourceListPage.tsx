import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api } from '../api/client'
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
    <section>
      <PageHeader title={`Resources: ${type}`} actions={<Link to={`/resource-types/${type}/new`}>Create Resource</Link>} />
      <ErrorBanner message={error} />
      <ResourceListTable type={type} columns={cols} rows={rows} />
    </section>
  )
}
