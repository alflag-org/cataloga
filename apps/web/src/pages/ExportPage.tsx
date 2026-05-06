import { useState } from 'react'
import { api } from '../api/client'
import { Button } from '../components/Button'
import { DataCard } from '../components/DataCard'
import { ErrorBanner } from '../components/ErrorBanner'
import { PageHeader } from '../components/PageHeader'

export function ExportPage() {
  const [yaml, setYaml] = useState('')
  const [error, setError] = useState<string | null>(null)

  return (
    <section className="space-y-5">
      <PageHeader title="Export" subtitle="Export current catalog as YAML" />
      <ErrorBanner message={error} />
      <DataCard>
        <div className="space-y-4">
          <Button onClick={() => api.exportYaml().then(setYaml).catch((e) => setError(e.message))}>Export</Button>
          <pre className="max-h-[60vh] overflow-auto rounded-lg bg-gray-950 p-4 text-xs text-gray-100">{yaml}</pre>
        </div>
      </DataCard>
    </section>
  )
}
