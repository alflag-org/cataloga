import { NavLink } from 'react-router-dom'

const primaryItems = [
  { to: '/', label: 'Dashboard' },
  { to: '/resources', label: 'Resources' }
]

const administrationItems = [
  { to: '/resource-types', label: 'Resource Types' },
  { to: '/import', label: 'Import' },
  { to: '/export', label: 'Export' }
]

function navClass(isActive: boolean): string {
  return `block rounded-xl px-3 py-2 text-sm font-medium ${
    isActive ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
  }`
}

export function Sidebar() {
  return (
    <aside className="hidden w-64 shrink-0 border-r border-gray-200 bg-white lg:block">
      <div className="p-5">
        <p className="text-lg font-semibold text-gray-900">Cataloga</p>
      </div>
      <nav className="space-y-1 px-3 pb-4">
        {primaryItems.map((item) => (
          <NavLink key={item.to} to={item.to} end={item.to === '/'} className={({ isActive }) => navClass(isActive)}>
            {item.label}
          </NavLink>
        ))}

        <div className="px-3 pt-4 text-xs font-semibold uppercase tracking-wide text-gray-500">Administration</div>
        {administrationItems.map((item) => (
          <NavLink key={item.to} to={item.to} className={({ isActive }) => navClass(isActive)}>
            {item.label}
          </NavLink>
        ))}
      </nav>
    </aside>
  )
}
