import { Link, Outlet } from 'react-router-dom'

export function Layout() {
  return (
    <div className="layout">
      <header className="topnav">
        <Link to="/">Dashboard</Link>
        <Link to="/resource-types">Resource Types</Link>
        <Link to="/import">Import</Link>
        <Link to="/export">Export</Link>
        <Link to="/settings">Settings</Link>
      </header>
      <main className="content">
        <Outlet />
      </main>
    </div>
  )
}
