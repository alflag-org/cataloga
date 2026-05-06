import { DataCard } from '../components/DataCard'
import { PageHeader } from '../components/PageHeader'

export function SettingsPage() {
  return (
    <section className="space-y-5">
      <PageHeader title="Settings" subtitle="Worker and storage settings are configured by environment bindings." />
      <DataCard>
        <p className="text-sm text-gray-700">No editable settings in web UI yet.</p>
      </DataCard>
    </section>
  )
}
