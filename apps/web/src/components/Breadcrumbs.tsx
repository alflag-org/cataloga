import { Link, useLocation } from "react-router-dom";
import { useI18n } from "../i18n";

function toLabel(pathname: string, t: (key: string) => string): string {
  if (pathname === "/") return t("Dashboard");

  const parts = pathname.split("/").filter(Boolean);
  if (parts[0] === "resources") {
    if (parts.length === 1) return t("Resources");
    if (parts.length === 2) return `${t("Resources")} / ${parts[1]}`;
    if (parts.length === 3)
      return `${t("Resources")} / ${parts[1]} / ${parts[2]}`;
    if (parts.length === 4 && parts[3] === "edit")
      return `${t("Resources")} / ${parts[1]} / ${parts[2]} / ${t("Edit")}`;
    if (parts.length === 3 && parts[2] === "new")
      return `${t("Resources")} / ${parts[1]} / ${t("Create Resource")}`;
  }

  if (parts[0] === "resource-types") {
    if (parts.length === 1)
      return `${t("Administration")} / ${t("Resource Types")}`;
    if (parts.length === 2 && parts[1] === "new")
      return `${t("Administration")} / ${t("Resource Types")} / ${t("Create Resource Type")}`;
    if (parts.length === 3 && parts[2] === "edit")
      return `${t("Administration")} / ${t("Resource Types")} / ${parts[1]} / Edit schema`;
  }

  if (pathname === "/import") return `${t("Administration")} / ${t("Import")}`;
  if (pathname === "/export") return `${t("Administration")} / ${t("Export")}`;
  if (pathname === "/validation")
    return `${t("Administration")} / ${t("Validation")}`;
  if (pathname === "/field-types")
    return `${t("Administration")} / Field Types`;

  return t("Dashboard");
}

export function Breadcrumbs() {
  const { t } = useI18n();
  const location = useLocation();
  const label = toLabel(location.pathname, t);

  return (
    <div className="flex items-center gap-2 text-sm text-gray-700">
      <Link to="/" className="text-gray-500 hover:text-gray-900">
        Cataloga
      </Link>
      <span className="text-gray-400">/</span>
      <span className="font-medium text-gray-900">{label}</span>
    </div>
  );
}
