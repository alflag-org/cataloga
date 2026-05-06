import { useState } from 'react'
import { api } from '../api/client'
import { Button } from '../components/Button'
import { DataCard } from '../components/DataCard'
import { ErrorBanner } from '../components/ErrorBanner'
import { PageHeader } from '../components/PageHeader'
import { TextareaInput } from '../components/TextareaInput'

export function ImportPage() {
  const [yaml, setYaml] = useState('')
  const [error, setError] = useState<string | null>(null)

  return (
    <section className="space-y-5">
      <PageHeader title="Import" subtitle="Import YAML snapshot to API" />
      <ErrorBanner message={error} />
      <DataCard>
        <div className="space-y-4">
          <TextareaInput rows={20} value={yaml} onChange={(e) => setYaml(e.target.value)} />
          <Button onClick={() => api.importYaml(yaml).catch((e) => setError(e.message))}>Import</Button>
        </div>
      </DataCard>
    </section>
  )
}
