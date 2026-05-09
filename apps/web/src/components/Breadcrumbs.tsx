import { Link, useLocation } from "react-router-dom";
import { useI18n } from "../i18n";

type Crumb = {
  to?: string;
  label: string;
};

function toCrumbs(pathname: string, t: (key: string) => string): Crumb[] {
  const root: Crumb[] = [{ to: "/", label: "Cataloga" }];
  if (pathname === "/") return [...root, { label: t("Dashboard") }];

  if (pathname === "/graph") return [...root, { label: t("Graph") }];

  if (pathname === "/import")
    return [
      ...root,
      { to: "/resource-types", label: t("Administration") },
      { label: t("Import") },
    ];

  if (pathname === "/export")
    return [
      ...root,
      { to: "/resource-types", label: t("Administration") },
      { label: t("Export") },
    ];

  if (pathname === "/validation")
    return [
      ...root,
      { to: "/resource-types", label: t("Administration") },
      { label: t("Validation") },
    ];

  if (pathname === "/field-types")
    return [
      ...root,
      { to: "/resource-types", label: t("Administration") },
      { label: t("Field Types") },
    ];

  const parts = pathname.split("/").filter(Boolean);
  if (parts[0] === "resources") {
    const crumbs: Crumb[] = [
      ...root,
      { to: "/resources", label: t("Resources") },
    ];
    if (parts.length >= 2) {
      crumbs.push({ to: `/resources/${parts[1]}`, label: parts[1] });
    }
    if (parts.length === 3 && parts[2] === "new") {
      crumbs.push({ label: t("Create Resource") });
      return crumbs;
    }
    if (parts.length >= 3) {
      crumbs.push({
        to: `/resources/${parts[1]}/${parts[2]}`,
        label: parts[2],
      });
    }
    if (parts.length === 4 && parts[3] === "edit") {
      crumbs.push({ label: t("Edit") });
    }
    return crumbs;
  }

  if (parts[0] === "resource-types") {
    const crumbs: Crumb[] = [
      ...root,
      { to: "/resource-types", label: t("Administration") },
      { to: "/resource-types", label: t("Resource Types") },
    ];
    if (parts.length === 2 && parts[1] === "new") {
      crumbs.push({ label: t("Create Resource Type") });
      return crumbs;
    }
    if (parts.length >= 2) {
      crumbs.push({ to: `/resources/${parts[1]}`, label: parts[1] });
    }
    if (parts.length === 3 && parts[2] === "edit") {
      crumbs.push({ label: "Edit schema" });
    }
    return crumbs;
  }

  return [...root, { label: t("Dashboard") }];
}

export function Breadcrumbs() {
  const { t } = useI18n();
  const location = useLocation();
  const crumbs = toCrumbs(location.pathname, t);

  return (
    <div className="ja-text-crisp flex flex-wrap items-center gap-2 text-sm text-gray-700">
      {crumbs.map((crumb, index) => {
        const isLast = index === crumbs.length - 1;
        return (
          <span
            key={`${crumb.label}-${index}`}
            className="flex items-center gap-2"
          >
            {crumb.to && !isLast ? (
              <Link to={crumb.to} className="text-gray-500 hover:text-gray-900">
                {crumb.label}
              </Link>
            ) : (
              <span
                className={
                  isLast ? "font-medium text-gray-900" : "text-gray-500"
                }
              >
                {crumb.label}
              </span>
            )}
            {!isLast ? <span className="text-gray-400">/</span> : null}
          </span>
        );
      })}
    </div>
  );
}
