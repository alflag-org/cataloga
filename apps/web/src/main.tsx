import React from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { Layout } from './components/Layout'
import { DashboardPage } from './pages/DashboardPage'
import { ExportPage } from './pages/ExportPage'
import { ImportPage } from './pages/ImportPage'
import { ResourceCreatePage } from './pages/ResourceCreatePage'
import { ResourceDetailPage } from './pages/ResourceDetailPage'
import { ResourceEditPage } from './pages/ResourceEditPage'
import { ResourceListPage } from './pages/ResourceListPage'
import { ResourceTypeCreatePage } from './pages/ResourceTypeCreatePage'
import { ResourceTypeEditPage } from './pages/ResourceTypeEditPage'
import { ResourceTypeListPage } from './pages/ResourceTypeListPage'
import { SettingsPage } from './pages/SettingsPage'
import { ValidationPage } from './pages/ValidationPage'
import './styles.css'

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route element={<Layout />}>
          <Route path="/" element={<DashboardPage />} />
          <Route path="/resource-types" element={<ResourceTypeListPage />} />
          <Route path="/resource-types/new" element={<ResourceTypeCreatePage />} />
          <Route path="/resource-types/:type" element={<ResourceListPage />} />
          <Route path="/resource-types/:type/edit" element={<ResourceTypeEditPage />} />
          <Route path="/resource-types/:type/new" element={<ResourceCreatePage />} />
          <Route path="/resource-types/:type/:id" element={<ResourceDetailPage />} />
          <Route path="/resource-types/:type/:id/edit" element={<ResourceEditPage />} />
          <Route path="/import" element={<ImportPage />} />
          <Route path="/export" element={<ExportPage />} />
          <Route path="/settings" element={<SettingsPage />} />
          <Route path="/validation" element={<ValidationPage />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Route>
      </Routes>
    </BrowserRouter>
  )
}

createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
)
