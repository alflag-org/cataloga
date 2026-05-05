import { useState } from 'react'
import { api } from '../api/client'
import { ErrorBanner } from '../components/ErrorBanner'
import { PageHeader } from '../components/PageHeader'

export function ExportPage() {
  const [yaml, setYaml] = useState('')
  const [error, setError] = useState<string | null>(null)

  return (
    <section>
      <PageHeader title="Export" />
      <ErrorBanner message={error} />
      <button onClick={() => api.exportYaml().then(setYaml).catch((e) => setError(e.message))}>Export</button>
      <pre>{yaml}</pre>
    </section>
  )
}
