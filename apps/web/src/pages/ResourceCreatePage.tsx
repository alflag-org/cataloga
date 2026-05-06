import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { api } from '../api/client'
import { ErrorBanner } from '../components/ErrorBanner'
import { PageHeader } from '../components/PageHeader'
import { ResourceForm } from '../components/ResourceForm'
import { defaultResource, type ResourceType } from '../types'

export function ResourceCreatePage() {
  const { type = '' } = useParams()
  const navigate = useNavigate()
  const [rt, setRt] = useState<ResourceType | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    api.getResourceType(type).then(setRt).catch((e) => setError(e.message))
  }, [type])

  if (!rt) return <ErrorBanner message={error || 'loading'} />

  return (
    <section className="space-y-5">
      <PageHeader title={`Create Resource: ${type}`} />
      <ResourceForm
        resourceType={rt}
        initial={defaultResource(type)}
        mode="create"
        onSubmit={async (resource) => {
          await api.createResource(type, resource)
          navigate(`/resource-types/${type}`)
        }}
      />
    </section>
  )
}
