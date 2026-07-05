import { Link } from "react-router-dom";
import { deriveDisplayLabel } from "../types";
import type { GraphEdge, GraphNode } from "../graph/graphLayout";
import { useI18n } from "../i18n";

type Props = {
  selectedNode: GraphNode | null;
  edges: GraphEdge[];
  nodesByKey: Map<string, GraphNode>;
  suggestedNodes: GraphNode[];
  onSelectNode: (key: string) => void;
  onFocusSelected: () => void;
  onClearSelected: () => void;
  relationDepth: 1 | 2;
  onRelationDepthChange: (value: 1 | 2) => void;
};

export function GraphDetailsPanel({
  selectedNode,
  edges,
  nodesByKey,
  suggestedNodes,
  onSelectNode,
  onFocusSelected,
  onClearSelected,
  relationDepth,
  onRelationDepthChange,
}: Props) {
  const { t } = useI18n();
  if (!selectedNode) {
    return (
      <aside className="rounded-2xl border border-gray-200 bg-white p-4">
        <h3 className="text-xs font-semibold uppercase tracking-wide text-gray-500">
          {t("Selected Resource")}
        </h3>
        <p className="mt-3 text-sm text-gray-600">
          {t("Select a Resource to inspect Relations.")}
        </p>
        {suggestedNodes.length ? (
          <div className="mt-5">
            <p className="text-sm font-medium text-gray-900">
              {t("Suggested Resources")}
            </p>
            <ul className="mt-2 space-y-1">
              {suggestedNodes.map((node) => (
                <li key={node.key}>
                  <button
                    type="button"
                    onClick={() => onSelectNode(node.key)}
                    className="flex min-h-11 w-full items-center justify-between gap-3 rounded-lg px-2 text-left text-xs text-gray-700 transition hover:bg-gray-50 hover:text-blue-700 focus:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                  >
                    <span className="min-w-0 truncate">
                      {node.typeTitle} / {node.name || node.resourceId}
                    </span>
                    <span className="shrink-0 text-gray-500">
                      {node.degree} {t("Relations")}
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          </div>
        ) : null}
      </aside>
    );
  }

  const outgoing = edges.filter((edge) => edge.source === selectedNode.key);
  const incoming = edges.filter((edge) => edge.target === selectedNode.key);
  const relationLabel = (key: string) => {
    const node = nodesByKey.get(key);
    if (!node) return key;
    return `${node.typeTitle} / ${node.name || node.resourceId}`;
  };
  const renderRelationButton = (
    edge: GraphEdge,
    targetKey: string,
    index: number,
  ) => (
    <li key={`${edge.source}-${edge.target}-${index}`}>
      <button
        type="button"
        onClick={() => onSelectNode(targetKey)}
        className="min-h-11 w-full rounded-lg px-2 py-2 text-left text-xs text-gray-700 transition hover:bg-gray-50 hover:text-blue-700 focus:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
      >
        <span className="font-medium">{deriveDisplayLabel(edge.field)}:</span>{" "}
        {relationLabel(targetKey)}
      </button>
    </li>
  );

  return (
    <aside className="rounded-2xl border border-gray-200 bg-white p-4">
      <h3 className="text-xs font-semibold uppercase tracking-wide text-gray-500">
        {t("Selected Resource")}
      </h3>
      <div className="mt-3 space-y-3 text-sm">
        <div>
          <p>
            <span className="font-medium">{t("Type")}:</span>{" "}
            {selectedNode.typeTitle}
          </p>
          <p>
            <span className="font-medium">{t("Name")}:</span>{" "}
            {selectedNode.name}
          </p>
          <p>
            <span className="font-medium">{t("ID")}:</span>{" "}
            {selectedNode.resourceId}
          </p>
          <p>
            <span className="font-medium">{t("Relations")}:</span>{" "}
            {selectedNode.degree}
          </p>
        </div>

        <div className="flex flex-wrap gap-2">
          <Link
            to={`/resources/${selectedNode.typeId}/${selectedNode.resourceId}`}
            className="inline-flex min-h-11 items-center rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:ring-offset-2"
          >
            {t("Open Resource")}
          </Link>
          <button
            type="button"
            onClick={onFocusSelected}
            className="inline-flex min-h-11 items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
          >
            {t("Focus Relations")}
          </button>
          <button
            type="button"
            onClick={onClearSelected}
            className="inline-flex min-h-11 items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
          >
            {t("Clear")}
          </button>
        </div>

        <div>
          <p className="text-xs font-semibold uppercase tracking-wide text-gray-500">
            {t("Relation View")}
          </p>
          <div
            className="mt-1 grid w-32 grid-cols-2 overflow-hidden rounded-lg border border-gray-300 bg-white"
            role="group"
            aria-label={t("Relation View")}
          >
            {[1, 2].map((value) => {
              const depth = value as 1 | 2;
              const active = relationDepth === depth;
              return (
                <button
                  key={depth}
                  type="button"
                  onClick={() => onRelationDepthChange(depth)}
                  aria-label={`${depth} ${t("Relations")}`}
                  aria-pressed={active}
                  className={[
                    "min-h-11 border-r border-gray-200 px-3 py-2 text-xs font-semibold transition last:border-r-0 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500/40",
                    active
                      ? "bg-blue-600 text-white"
                      : "text-gray-700 hover:bg-gray-50",
                  ].join(" ")}
                >
                  {depth}
                </button>
              );
            })}
          </div>
        </div>

        <div>
          <p className="font-medium text-gray-900">{t("Outgoing Relations")}</p>
          {outgoing.length === 0 ? (
            <p className="text-xs text-gray-600">
              {t("No outgoing Relations.")}
            </p>
          ) : (
            <ul className="mt-1 space-y-1">
              {outgoing.map((edge, index) =>
                renderRelationButton(edge, edge.target, index),
              )}
            </ul>
          )}
        </div>

        <div>
          <p className="font-medium text-gray-900">{t("Incoming Relations")}</p>
          {incoming.length === 0 ? (
            <p className="text-xs text-gray-600">
              {t("No incoming Relations.")}
            </p>
          ) : (
            <ul className="mt-1 space-y-1">
              {incoming.map((edge, index) =>
                renderRelationButton(edge, edge.source, index),
              )}
            </ul>
          )}
        </div>
      </div>
    </aside>
  );
}
