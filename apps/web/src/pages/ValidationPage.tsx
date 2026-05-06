import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api/client'
import { DataCard } from '../components/DataCard'
import { ErrorBanner } from '../components/ErrorBanner'
import { PageHeader } from '../components/PageHeader'
import type { ValidationResult } from '../types'

export function ValidationPage() {
  const [result, setResult] = useState<ValidationResult | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    api.getValidation().then(setResult).catch((e) => setError(e.message))
  }, [])

  return (
    <section className="space-y-5">
      <PageHeader title="Validation" />
      <ErrorBanner message={error} />
      {result ? (
        <DataCard>
          <div className="space-y-3 text-sm">
            <p>Status: {result.status === 'ok' ? 'OK' : 'Failed'}</p>
            <p>Errors: {result.errors.length}</p>
            <p>Warnings: {result.warnings.length}</p>
            {result.errors.map((item, idx) => (
              <div key={idx} className="rounded border border-red-200 bg-red-50 px-3 py-2 text-red-900">
                <p>Resource: {item.resource_type && item.resource_id ? `${item.resource_type} / ${item.resource_id}` : '-'}</p>
                <p>Field: {item.field || '-'}</p>
                <p>Message: {item.message}</p>
                {item.resource_type && item.resource_id ? (
                  <Link className="text-xs underline" to={`/resources/${item.resource_type}/${item.resource_id}`}>Show</Link>
                ) : null}
              </div>
            ))}
          </div>
        </DataCard>
      ) : null}
    </section>
  )
}
