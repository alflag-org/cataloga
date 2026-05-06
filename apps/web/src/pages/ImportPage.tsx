import { useState } from 'react'
import { api } from '../api/client'
import { Button } from '../components/Button'
import { DataCard } from '../components/DataCard'
import { ErrorBanner } from '../components/ErrorBanner'
import { PageHeader } from '../components/PageHeader'
import { TextareaInput } from '../components/TextareaInput'
import type { ImportPreviewResult } from '../types'

export function ImportPage() {
  const [yaml, setYaml] = useState('')
  const [preview, setPreview] = useState<ImportPreviewResult | null>(null)
  const [error, setError] = useState<string | null>(null)

  return (
    <section className="space-y-5">
      <PageHeader title="Import" subtitle="Import YAML snapshot to API" />
      <ErrorBanner message={error} />
      <DataCard>
        <div className="space-y-4">
          <TextareaInput rows={20} value={yaml} onChange={(e) => setYaml(e.target.value)} />
          <div className="flex gap-2">
            <Button
              variant="secondary"
              onClick={async () => {
                try {
                  setError(null)
                  setPreview(await api.importPreview(yaml))
                } catch (e) {
                  setError(e instanceof Error ? e.message : String(e))
                }
              }}
            >
              Preview Import
            </Button>
            <Button
              onClick={async () => {
                try {
                  setError(null)
                  await api.importApply(yaml)
                } catch (e) {
                  setError(e instanceof Error ? e.message : String(e))
                }
              }}
              disabled={!preview || preview.validation_errors.length > 0}
            >
              Apply Import
            </Button>
          </div>
          {preview ? (
            <div className="space-y-2 text-sm">
              <p>Resource Types to create: {preview.resource_types_to_create.length}</p>
              <p>Resource Types to update: {preview.resource_types_to_update.length}</p>
              <p>Resources to create: {preview.resources_to_create.length}</p>
              <p>Resources to update: {preview.resources_to_update.length}</p>
              <p>Validation errors: {preview.validation_errors.length}</p>
            </div>
          ) : null}
        </div>
      </DataCard>
    </section>
  )
}
