import React from "react";
import { createRoot } from "react-dom/client";
import {
  BrowserRouter,
  Navigate,
  Route,
  Routes,
  useParams,
} from "react-router-dom";
import { Layout } from "./components/Layout";
import { DashboardPage } from "./pages/DashboardPage";
import { ExportPage } from "./pages/ExportPage";
import { FieldTypesGuidePage } from "./pages/FieldTypesGuidePage";
import { ImportPage } from "./pages/ImportPage";
import { ResourceCreatePage } from "./pages/ResourceCreatePage";
import { ResourceDetailPage } from "./pages/ResourceDetailPage";
import { ResourceEditPage } from "./pages/ResourceEditPage";
import { ResourceListPage } from "./pages/ResourceListPage";
import { ResourceTypeCreatePage } from "./pages/ResourceTypeCreatePage";
import { ResourceTypeEditPage } from "./pages/ResourceTypeEditPage";
import { ResourceTypeListPage } from "./pages/ResourceTypeListPage";
import { ResourcesIndexPage } from "./pages/ResourcesIndexPage";
import { ValidationPage } from "./pages/ValidationPage";
import "./styles.css";

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route element={<Layout />}>
          <Route path="/" element={<DashboardPage />} />

          <Route path="/resources" element={<ResourcesIndexPage />} />
          <Route path="/graph" element={<Navigate to="/" replace />} />
          <Route path="/resources/:type" element={<ResourceListPage />} />
          <Route path="/resources/:type/new" element={<ResourceCreatePage />} />
          <Route path="/resources/:type/:id" element={<ResourceDetailPage />} />
          <Route
            path="/resources/:type/:id/edit"
            element={<ResourceEditPage />}
          />

          <Route path="/resource-types" element={<ResourceTypeListPage />} />
          <Route
            path="/resource-types/new"
            element={<ResourceTypeCreatePage />}
          />
          <Route
            path="/resource-types/:type/edit"
            element={<ResourceTypeEditPage />}
          />

          <Route
            path="/resource-types/:type"
            element={<RedirectResourceTypeToResources />}
          />
          <Route
            path="/resource-types/:type/new"
            element={<RedirectResourceTypeNewToResourcesNew />}
          />
          <Route
            path="/resource-types/:type/:id"
            element={<RedirectResourceTypeDetailToResourcesDetail />}
          />
          <Route
            path="/resource-types/:type/:id/edit"
            element={<RedirectResourceTypeEditToResourcesEdit />}
          />

          <Route path="/import" element={<ImportPage />} />
          <Route path="/export" element={<ExportPage />} />
          <Route path="/validation" element={<ValidationPage />} />
          <Route path="/field-types" element={<FieldTypesGuidePage />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Route>
      </Routes>
    </BrowserRouter>
  );
}

function RedirectResourceTypeToResources() {
  const { type = "" } = useParams();
  return <Navigate to={`/resources/${type}`} replace />;
}

function RedirectResourceTypeNewToResourcesNew() {
  const { type = "" } = useParams();
  return <Navigate to={`/resources/${type}/new`} replace />;
}

function RedirectResourceTypeDetailToResourcesDetail() {
  const { type = "", id = "" } = useParams();
  return <Navigate to={`/resources/${type}/${id}`} replace />;
}

function RedirectResourceTypeEditToResourcesEdit() {
  const { type = "", id = "" } = useParams();
  return <Navigate to={`/resources/${type}/${id}/edit`} replace />;
}

createRoot(document.getElementById("root")!).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
);
