import { NavLink } from 'react-router-dom'

const navItems = [
  { to: '/', label: 'Dashboard' },
  { to: '/resource-types', label: 'Resource Types' },
  { to: '/import', label: 'Import' },
  { to: '/export', label: 'Export' },
  { to: '/validation', label: 'Validation' },
  { to: '/settings', label: 'Settings' }
]

export function Sidebar() {
  return (
    <aside className="hidden w-64 shrink-0 border-r border-gray-200 bg-white lg:block">
      <div className="p-5">
        <p className="text-lg font-semibold text-gray-900">Cataloga</p>
        <p className="text-xs text-gray-500">Resource Admin</p>
      </div>
      <nav className="space-y-1 px-3 pb-4">
        {navItems.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            end={item.to === '/'}
            className={({ isActive }) =>
              `block rounded-xl px-3 py-2 text-sm font-medium ${
                isActive ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
              }`
            }
          >
            {item.label}
          </NavLink>
        ))}
      </nav>
    </aside>
  )
}
