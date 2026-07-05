import { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import type { Resource, ResourceType } from "../types";
import { buildGraphData, computeLayout } from "../graph/graphLayout";
import { useI18n } from "../i18n";
import { GraphControls, type GraphViewMode } from "./GraphControls";
import { SigmaGraphCanvas } from "./SigmaGraphCanvas";

export type ResourceGraphProps = {
  types: ResourceType[];
  resourcesByType: Record<string, Resource[]>;
  compact?: boolean;
  expanded?: boolean;
};

const COMPACT_OVERVIEW_LIMIT = 32;
const DEFAULT_OVERVIEW_LIMIT = 40;
const EXPANDED_OVERVIEW_LIMIT = 56;

function neighborSets(edges: Array<{ source: string; target: string }>) {
  const map = new Map<string, Set<string>>();
  for (const edge of edges) {
    if (!map.has(edge.source)) map.set(edge.source, new Set());
    if (!map.has(edge.target)) map.set(edge.target, new Set());
    map.get(edge.source)!.add(edge.target);
    map.get(edge.target)!.add(edge.source);
  }
  return map;
}

function collectNeighborhoodKeys(
  startKey: string,
  edges: Array<{ source: string; target: string }>,
  depth: number,
) {
  const neighbors = neighborSets(edges);
  const seen = new Set<string>([startKey]);
  let frontier = new Set<string>([startKey]);

  for (let level = 0; level < depth; level += 1) {
    const next = new Set<string>();
    for (const key of frontier) {
      for (const neighbor of neighbors.get(key) ?? []) {
        if (seen.has(neighbor)) continue;
        seen.add(neighbor);
        next.add(neighbor);
      }
    }
    frontier = next;
    if (!frontier.size) break;
  }

  return seen;
}

function pickOverviewKeys(
  nodes: Array<{ key: string; degree: number; group: string; name: string }>,
  edges: Array<{ source: string; target: string }>,
  limit: number,
) {
  if (nodes.length <= limit) return new Set(nodes.map((node) => node.key));

  const allowedKeys = new Set(nodes.map((node) => node.key));
  const neighbors = neighborSets(edges);
  const degreeByKey = new Map(nodes.map((node) => [node.key, node.degree]));
  const sortByImportance = (a: string, b: string) =>
    (degreeByKey.get(b) ?? 0) - (degreeByKey.get(a) ?? 0) || a.localeCompare(b);
  const seeds = [...nodes].sort(
    (a, b) =>
      (b.degree ?? 0) - (a.degree ?? 0) ||
      a.group.localeCompare(b.group) ||
      a.name.localeCompare(b.name) ||
      a.key.localeCompare(b.key),
  );
  const selected = new Set<string>();

  for (const seed of seeds) {
    if (selected.size >= limit) break;
    selected.add(seed.key);

    const related = [...(neighbors.get(seed.key) ?? [])]
      .filter((key) => allowedKeys.has(key))
      .sort(sortByImportance);
    for (const key of related) {
      if (selected.size >= limit) break;
      selected.add(key);
    }
  }

  return selected;
}

export function ResourceGraph({
  types,
  resourcesByType,
  compact = false,
  expanded = false,
}: ResourceGraphProps) {
  const { t } = useI18n();
  const navigate = useNavigate();
  const [isExpanded, setIsExpanded] = useState(expanded);
  const [search, setSearch] = useState("");
  const [groupFilter, setGroupFilter] = useState("All");
  const [showMode, setShowMode] = useState<"all" | "connected" | "isolated">(
    "all",
  );
  const defaultViewMode: GraphViewMode =
    compact && !expanded ? "overview" : "all";
  const [viewMode, setViewMode] = useState<GraphViewMode>(defaultViewMode);
  const [selectedKey, setSelectedKey] = useState<string | null>(null);
  const [hoveredKey, setHoveredKey] = useState<string | null>(null);
  const [manualNodePositions, setManualNodePositions] = useState<
    Record<string, { x: number; y: number }>
  >({});

  const searchQuery = search.trim().toLowerCase();
  const overviewLimit = isExpanded
    ? EXPANDED_OVERVIEW_LIMIT
    : compact
      ? COMPACT_OVERVIEW_LIMIT
      : DEFAULT_OVERVIEW_LIMIT;

  const layoutGraph = useMemo(
    () => computeLayout(buildGraphData(types, resourcesByType)),
    [types, resourcesByType],
  );

  const baseGraph = useMemo(
    () => ({
      nodes: layoutGraph.nodes.map((node) => {
        const manualPosition = manualNodePositions[node.key];
        if (!manualPosition) return node;
        return {
          ...node,
          x: manualPosition.x,
          y: manualPosition.y,
        };
      }),
      edges: layoutGraph.edges,
    }),
    [layoutGraph, manualNodePositions],
  );

  useEffect(() => {
    const validKeys = new Set(layoutGraph.nodes.map((node) => node.key));
    setManualNodePositions((current) => {
      const next = Object.fromEntries(
        Object.entries(current).filter(([key]) => validKeys.has(key)),
      );
      return Object.keys(next).length === Object.keys(current).length
        ? current
        : next;
    });
  }, [layoutGraph.nodes]);

  const groups = useMemo(
    () => [...new Set(baseGraph.nodes.map((node) => node.group))].sort(),
    [baseGraph.nodes],
  );

  const nodesByKey = useMemo(
    () => new Map(baseGraph.nodes.map((node) => [node.key, node])),
    [baseGraph.nodes],
  );

  const controlFilteredNodes = useMemo(() => {
    return baseGraph.nodes.filter((node) => {
      if (groupFilter !== "All" && node.group !== groupFilter) return false;
      if (showMode === "connected" && node.degree === 0) return false;
      if (showMode === "isolated" && node.degree > 0) return false;
      return true;
    });
  }, [baseGraph.nodes, groupFilter, showMode]);

  const controlVisibleKeys = useMemo(
    () => new Set(controlFilteredNodes.map((node) => node.key)),
    [controlFilteredNodes],
  );

  const controlEdges = useMemo(
    () =>
      baseGraph.edges.filter(
        (edge) =>
          controlVisibleKeys.has(edge.source) &&
          controlVisibleKeys.has(edge.target),
      ),
    [baseGraph.edges, controlVisibleKeys],
  );

  const searchFilteredNodes = useMemo(() => {
    if (!searchQuery) return controlFilteredNodes;
    return controlFilteredNodes.filter((node) =>
      [node.name, node.resourceId, node.typeTitle, node.typeId, node.group]
        .join(" ")
        .toLowerCase()
        .includes(searchQuery),
    );
  }, [controlFilteredNodes, searchQuery]);

  const filteredNodes = useMemo(() => {
    if (
      viewMode === "focus" &&
      selectedKey &&
      controlVisibleKeys.has(selectedKey)
    ) {
      const focusKeys = collectNeighborhoodKeys(selectedKey, controlEdges, 1);
      return controlFilteredNodes.filter((node) => focusKeys.has(node.key));
    }

    if (viewMode === "overview" && !searchQuery) {
      const overviewKeys = pickOverviewKeys(
        controlFilteredNodes,
        controlEdges,
        overviewLimit,
      );
      return controlFilteredNodes.filter((node) => overviewKeys.has(node.key));
    }

    return searchFilteredNodes;
  }, [
    controlEdges,
    controlFilteredNodes,
    controlVisibleKeys,
    overviewLimit,
    searchFilteredNodes,
    searchQuery,
    selectedKey,
    viewMode,
  ]);

  const visibleKeys = useMemo(
    () => new Set(filteredNodes.map((node) => node.key)),
    [filteredNodes],
  );

  const filteredEdges = useMemo(
    () =>
      baseGraph.edges.filter(
        (edge) => visibleKeys.has(edge.source) && visibleKeys.has(edge.target),
      ),
    [baseGraph.edges, visibleKeys],
  );

  const filteredGraph = useMemo(
    () => ({ nodes: filteredNodes, edges: filteredEdges }),
    [filteredEdges, filteredNodes],
  );

  const neighbors = useMemo(() => neighborSets(filteredEdges), [filteredEdges]);
  const activeKey = hoveredKey || selectedKey;
  const activeSet = useMemo(() => {
    if (!activeKey) return null;
    const set = new Set<string>([activeKey]);
    for (const key of neighbors.get(activeKey) ?? []) set.add(key);
    return set;
  }, [activeKey, neighbors]);

  useEffect(() => {
    if (
      viewMode === "focus" &&
      (!selectedKey || !controlVisibleKeys.has(selectedKey))
    ) {
      setViewMode(defaultViewMode);
    }
  }, [controlVisibleKeys, defaultViewMode, selectedKey, viewMode]);

  useEffect(() => {
    if (selectedKey && !visibleKeys.has(selectedKey)) setSelectedKey(null);
    if (hoveredKey && !visibleKeys.has(hoveredKey)) setHoveredKey(null);
  }, [hoveredKey, selectedKey, visibleKeys]);

  useEffect(() => {
    if (!isExpanded) return;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => {
      document.body.style.overflow = previousOverflow;
    };
  }, [isExpanded]);

  useEffect(() => {
    if (!isExpanded) return;
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setIsExpanded(false);
      }
    };
    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [isExpanded]);

  const selectNode = (key: string) => {
    setSelectedKey(key);
    setViewMode("focus");
  };

  const openNode = (key: string) => {
    const node = nodesByKey.get(key);
    if (!node) return;
    navigate(`/resources/${node.typeId}/${node.resourceId}`);
  };

  const clearSelectedNode = () => {
    setSelectedKey(null);
    setHoveredKey(null);
    if (viewMode === "focus") setViewMode(defaultViewMode);
  };

  const resetLayout = () => {
    setManualNodePositions({});
  };

  const hiddenResourceCount = Math.max(
    0,
    controlFilteredNodes.length - filteredGraph.nodes.length,
  );
  const hasManualLayout = Object.keys(manualNodePositions).length > 0;

  if (!baseGraph.nodes.length) {
    return (
      <div className="rounded-2xl border border-gray-200 bg-white p-6 text-sm text-gray-600">
        <p className="font-medium text-gray-900">{t("No resources")}</p>
        <p className="mt-1">
          {t("Create Resource Types and Resources to populate the graph.")}
        </p>
      </div>
    );
  }

  const renderGraphCanvas = (
    mode: "cooperative" | "greedy",
    showExpandButton: boolean,
    showCloseButton: boolean,
    panelClassName: string,
  ) => (
    <SigmaGraphCanvas
      graph={filteredGraph}
      activeKey={activeKey}
      activeSet={activeSet}
      selectedKey={selectedKey}
      viewMode={viewMode}
      hiddenResourceCount={hiddenResourceCount}
      hasManualLayout={hasManualLayout}
      mode={mode}
      showExpandButton={showExpandButton}
      showCloseButton={showCloseButton}
      panelClassName={panelClassName}
      onExpand={() => setIsExpanded(true)}
      onClose={() => setIsExpanded(false)}
      onSelectNode={selectNode}
      onOpenNode={openNode}
      onHoverNode={setHoveredKey}
      onClearHover={(key) =>
        setHoveredKey((prev) => (prev === key ? null : prev))
      }
      onClearSelected={clearSelectedNode}
      onResetLayout={resetLayout}
      onNodePositionChange={(key, position) => {
        setManualNodePositions((current) => ({
          ...current,
          [key]: position,
        }));
      }}
    />
  );

  return (
    <>
      {!isExpanded ? (
        <div className="space-y-3">
          <GraphControls
            search={search}
            onSearchChange={setSearch}
            viewMode={viewMode}
            onViewModeChange={setViewMode}
            canFocus={Boolean(selectedKey)}
            groups={groups}
            selectedGroup={groupFilter}
            onGroupChange={setGroupFilter}
            showMode={showMode}
            onShowModeChange={setShowMode}
            showViewControls={false}
          />

          <div className="grid gap-3">
            {renderGraphCanvas(
              "cooperative",
              true,
              false,
              "graph-panel relative h-[360px] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm md:h-[420px]",
            )}
          </div>
        </div>
      ) : null}

      {isExpanded ? (
        <div className="fixed inset-0 z-50 bg-gray-800/35 p-3 md:p-6">
          <div className="flex h-full flex-col rounded-2xl border border-gray-200 bg-gray-50 p-3 shadow-2xl md:p-4">
            <div className="mb-3 flex items-center justify-between gap-2">
              <h2 className="text-sm font-semibold text-gray-900">
                {t("Graph")}
              </h2>
              <button
                type="button"
                onClick={() => setIsExpanded(false)}
                className="min-h-11 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
              >
                {t("Close")}
              </button>
            </div>
            <div className="mb-3">
              <GraphControls
                search={search}
                onSearchChange={setSearch}
                viewMode={viewMode}
                onViewModeChange={setViewMode}
                canFocus={Boolean(selectedKey)}
                groups={groups}
                selectedGroup={groupFilter}
                onGroupChange={setGroupFilter}
                showMode={showMode}
                onShowModeChange={setShowMode}
                showViewControls={false}
              />
            </div>
            <div className="min-h-0 flex-1">
              {renderGraphCanvas(
                "greedy",
                false,
                false,
                "graph-panel relative h-full min-h-[420px] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm",
              )}
            </div>
          </div>
        </div>
      ) : null}
    </>
  );
}
