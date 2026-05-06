import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api/client'
import { Badge } from '../components/Badge'
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
      <PageHeader title="Validation" subtitle="Catalog validation result" />
      <ErrorBanner message={error} />
      {result ? (
        <DataCard>
          <div className="space-y-3">
            <p className="text-sm">
              Status: <Badge>{result.status}</Badge>
            </p>
            <p className="text-sm">Errors: {result.errors.length}</p>
            <p className="text-sm">Warnings: {result.warnings.length}</p>
            <div className="space-y-2">
              {result.errors.map((item, idx) => (
                <div key={idx} className="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900">
                  <p className="font-medium">{item.message}</p>
                  <p className="text-xs">severity={item.severity} field={item.field || '-'}</p>
                  {item.resource_type && item.resource_id ? (
                    <Link className="text-xs underline" to={`/resource-types/${item.resource_type}/${item.resource_id}`}>
                      {item.resource_type}/{item.resource_id}
                    </Link>
                  ) : item.resource_type ? (
                    <Link className="text-xs underline" to={`/resource-types/${item.resource_type}/edit`}>
                      {item.resource_type} (Resource Type)
                    </Link>
                  ) : (
                    <p className="text-xs">resource=-</p>
                  )}
                </div>
              ))}
            </div>
            {result.errors.length === 0 && result.warnings.length === 0 ? <p className="text-sm text-green-700">Catalog validation passed.</p> : null}
          </div>
        </DataCard>
      ) : null}
    </section>
  )
}
