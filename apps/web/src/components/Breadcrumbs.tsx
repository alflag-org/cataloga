import { Link, useLocation } from 'react-router-dom'

function toLabel(pathname: string): string {
  if (pathname === '/') return 'Dashboard'

  const parts = pathname.split('/').filter(Boolean)
  if (parts[0] === 'resources') {
    if (parts.length === 1) return 'Resources'
    if (parts.length === 2) return `Resources / ${parts[1]}`
    if (parts.length === 3) return `Resources / ${parts[1]} / ${parts[2]}`
    if (parts.length === 4 && parts[3] === 'edit') return `Resources / ${parts[1]} / ${parts[2]} / Edit`
    if (parts.length === 3 && parts[2] === 'new') return `Resources / ${parts[1]} / Create`
  }

  if (parts[0] === 'resource-types') {
    if (parts.length === 1) return 'Administration / Resource Types'
    if (parts.length === 2 && parts[1] === 'new') return 'Administration / Resource Types / Create Resource Type'
    if (parts.length === 3 && parts[2] === 'edit') return `Administration / Resource Types / ${parts[1]} / Edit schema`
  }

  if (pathname === '/import') return 'Administration / Import'
  if (pathname === '/export') return 'Administration / Export'
  if (pathname === '/validation') return 'Validation'
  if (pathname === '/graph') return 'View / Graph'

  return 'Dashboard'
}

export function Breadcrumbs() {
  const location = useLocation()
  const label = toLabel(location.pathname)

  return (
    <div className="flex items-center gap-2 text-sm text-gray-700">
      <Link to="/" className="text-gray-500 hover:text-gray-900">
        Cataloga
      </Link>
      <span className="text-gray-400">/</span>
      <span className="font-medium text-gray-900">{label}</span>
    </div>
  )
}
