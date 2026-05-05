import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api } from '../api/client'
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
    <section>
      <PageHeader title={`Resource: ${type}/${id}`} actions={<Link to={`/resource-types/${type}/${id}/edit`}>Edit</Link>} />
      <ErrorBanner message={error} />
      {resource ? <pre>{compactValue(resource)}</pre> : null}
    </section>
  )
}
