import { Breadcrumbs } from './Breadcrumbs'

export function Topbar() {
  return (
    <header className="sticky top-0 z-10 border-b border-gray-200 bg-white/95 backdrop-blur">
      <div className="mx-auto flex max-w-7xl items-center px-4 py-3 sm:px-6 lg:px-8">
        <Breadcrumbs />
      </div>
    </header>
  )
}
