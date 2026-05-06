import type { ReactNode } from 'react'

export function StatCard({ label, value, icon }: { label: string; value: ReactNode; icon?: ReactNode }) {
  return (
    <div className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
      <div className="mb-2 flex items-center justify-between">
        <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">{label}</p>
        {icon}
      </div>
      <p className="text-2xl font-semibold text-gray-900">{value}</p>
    </div>
  )
}
