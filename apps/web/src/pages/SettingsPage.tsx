import { api } from '../api/client'
import { Button } from '../components/Button'
import { DataCard } from '../components/DataCard'
import { PageHeader } from '../components/PageHeader'
import { buildHomeLabSampleResources, buildHomeLabTypes } from '../homeLabTemplate'

export function SettingsPage() {
  return (
    <section className="space-y-5">
      <PageHeader title="Settings" subtitle="Worker and storage settings are configured by environment bindings." />
      <DataCard>
        <div className="space-y-3">
          <p className="text-sm text-gray-700">Apply Home Lab Basic template to initialize Resource Types.</p>
          <Button
            variant="secondary"
            onClick={async () => {
              const existing = await api.listResourceTypes()
              const template = buildHomeLabTypes()
              const exists = template.some((t) => existing.some((x) => x.id === t.id))
              if (exists && !window.confirm('Some Resource Types already exist. Overwrite with Home Lab Basic template?')) return
              for (const rt of template) {
                await api.upsertResourceType(rt)
              }
              for (const r of buildHomeLabSampleResources()) {
                await api.createResource(r.metadata.type, r)
              }
              window.location.reload()
            }}
          >
            Apply Home Lab Basic Template
          </Button>
        </div>
      </DataCard>
    </section>
  )
}
