import { useState } from 'react'
import { api } from '../api/client'
import { ErrorBanner } from '../components/ErrorBanner'
import { PageHeader } from '../components/PageHeader'

export function ImportPage() {
  const [yaml, setYaml] = useState('')
  const [error, setError] = useState<string | null>(null)

  return (
    <section>
      <PageHeader title="Import" />
      <ErrorBanner message={error} />
      <textarea rows={20} value={yaml} onChange={(e) => setYaml(e.target.value)} />
      <button onClick={() => api.importYaml(yaml).catch((e) => setError(e.message))}>Import</button>
    </section>
  )
}
