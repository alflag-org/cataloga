import { Suspense, lazy } from "react";
import { useI18n } from "../i18n";
import type { ResourceGraphProps } from "./ResourceGraph";

const ResourceGraph = lazy(() =>
  import("./ResourceGraph").then((module) => ({
    default: module.ResourceGraph,
  })),
);

function ResourceGraphFallback({
  expanded = false,
}: Pick<ResourceGraphProps, "expanded">) {
  const { t } = useI18n();
  return (
    <div
      className={
        expanded
          ? "grid min-h-[560px] place-items-center rounded-lg border border-dashed border-gray-200 bg-gray-50 text-sm text-gray-600"
          : "grid h-[360px] place-items-center rounded-lg border border-dashed border-gray-200 bg-gray-50 text-sm text-gray-600"
      }
    >
      {t("Loading Relation View")}
    </div>
  );
}

export function LazyResourceGraph(props: ResourceGraphProps) {
  return (
    <Suspense fallback={<ResourceGraphFallback expanded={props.expanded} />}>
      <ResourceGraph {...props} />
    </Suspense>
  );
}
