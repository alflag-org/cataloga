import { useEffect, useMemo, useRef, useState, type PointerEvent } from "react";
import type { Resource, ResourceType } from "../types";
import { GraphControls } from "./GraphControls";
import { GraphDetailsPanel } from "./GraphDetailsPanel";
import {
  buildGraphData,
  clampScale,
  computeLayout,
  computeNodeRadius,
  fitViewportToGraph,
  type GraphViewport,
} from "../graph/graphLayout";
import { pickGroupColor } from "../graph/graphColors";
import { useI18n } from "../i18n";

type Props = {
  types: ResourceType[];
  resourcesByType: Record<string, Resource[]>;
  compact?: boolean;
};

const ZOOM_STEP = 1.12;

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

export function ResourceGraph({
  types,
  resourcesByType,
  compact = false,
}: Props) {
  const { t } = useI18n();
  const containerRef = useRef<HTMLDivElement | null>(null);
  const [search, setSearch] = useState("");
  const [groupFilter, setGroupFilter] = useState("All");
  const [showMode, setShowMode] = useState<"all" | "connected" | "isolated">(
    "all",
  );
  const [selectedKey, setSelectedKey] = useState<string | null>(null);
  const [hoveredKey, setHoveredKey] = useState<string | null>(null);
  const [dragging, setDragging] = useState(false);
  const [size, setSize] = useState({ width: 920, height: compact ? 360 : 420 });
  const [viewport, setViewport] = useState<GraphViewport>({
    x: 0,
    y: 0,
    scale: 1,
  });

  const dragState = useRef<{
    pointerId: number;
    startX: number;
    startY: number;
    viewportX: number;
    viewportY: number;
  } | null>(null);

  const baseGraph = useMemo(
    () => computeLayout(buildGraphData(types, resourcesByType)),
    [types, resourcesByType],
  );

  const groups = useMemo(
    () => [...new Set(baseGraph.nodes.map((node) => node.group))].sort(),
    [baseGraph.nodes],
  );

  const filteredNodes = useMemo(() => {
    const q = search.trim().toLowerCase();
    return baseGraph.nodes.filter((node) => {
      if (groupFilter !== "All" && node.group !== groupFilter) return false;
      if (showMode === "connected" && node.degree === 0) return false;
      if (showMode === "isolated" && node.degree > 0) return false;
      if (!q) return true;
      return [
        node.name,
        node.resourceId,
        node.typeTitle,
        node.typeId,
        node.group,
      ]
        .join(" ")
        .toLowerCase()
        .includes(q);
    });
  }, [baseGraph.nodes, groupFilter, search, showMode]);

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
    if (!containerRef.current) return;
    const observer = new ResizeObserver((entries) => {
      const rect = entries[0]?.contentRect;
      if (!rect) return;
      setSize({
        width: Math.max(360, Math.floor(rect.width)),
        height: Math.max(compact ? 360 : 420, Math.floor(rect.height)),
      });
    });
    observer.observe(containerRef.current);
    return () => observer.disconnect();
  }, [compact]);

  useEffect(() => {
    setViewport(fitViewportToGraph(filteredGraph, size.width, size.height));
  }, [filteredGraph, size.height, size.width]);

  useEffect(() => {
    if (selectedKey && !visibleKeys.has(selectedKey)) setSelectedKey(null);
    if (hoveredKey && !visibleKeys.has(hoveredKey)) setHoveredKey(null);
  }, [hoveredKey, selectedKey, visibleKeys]);

  const positionMap = useMemo(
    () => new Map(filteredGraph.nodes.map((node) => [node.key, node])),
    [filteredGraph.nodes],
  );

  const onPointerDown = (event: PointerEvent<SVGSVGElement>) => {
    if (event.button !== 0) return;
    const target = event.target as Element;
    if (target.closest("[data-node='true']")) return;
    dragState.current = {
      pointerId: event.pointerId,
      startX: event.clientX,
      startY: event.clientY,
      viewportX: viewport.x,
      viewportY: viewport.y,
    };
    setDragging(true);
    event.currentTarget.setPointerCapture(event.pointerId);
  };

  const onPointerMove = (event: PointerEvent<SVGSVGElement>) => {
    const state = dragState.current;
    if (!state || state.pointerId !== event.pointerId) return;
    const deltaX = event.clientX - state.startX;
    const deltaY = event.clientY - state.startY;
    setViewport((current) => ({
      ...current,
      x: state.viewportX + deltaX,
      y: state.viewportY + deltaY,
    }));
  };

  const endDrag = (event: PointerEvent<SVGSVGElement>) => {
    const state = dragState.current;
    if (!state || state.pointerId !== event.pointerId) return;
    dragState.current = null;
    setDragging(false);
    event.currentTarget.releasePointerCapture(event.pointerId);
  };

  const onWheel = (event: React.WheelEvent<SVGSVGElement>) => {
    event.preventDefault();
    const rect = event.currentTarget.getBoundingClientRect();
    const mouseX = event.clientX - rect.left;
    const mouseY = event.clientY - rect.top;
    setViewport((current) => {
      const oldScale = current.scale;
      const zoomFactor = event.deltaY < 0 ? ZOOM_STEP : 1 / ZOOM_STEP;
      const newScale = clampScale(oldScale * zoomFactor);
      const worldX = (mouseX - current.x) / oldScale;
      const worldY = (mouseY - current.y) / oldScale;
      return {
        x: mouseX - worldX * newScale,
        y: mouseY - worldY * newScale,
        scale: newScale,
      };
    });
  };

  const zoomFromCenter = (factor: number) => {
    setViewport((current) => {
      const centerX = size.width / 2;
      const centerY = size.height / 2;
      const newScale = clampScale(current.scale * factor);
      const worldX = (centerX - current.x) / current.scale;
      const worldY = (centerY - current.y) / current.scale;
      return {
        x: centerX - worldX * newScale,
        y: centerY - worldY * newScale,
        scale: newScale,
      };
    });
  };

  const zoomPercent = Math.round(viewport.scale * 100);
  const selectedNode =
    filteredGraph.nodes.find((node) => node.key === selectedKey) ?? null;

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

  return (
    <div className="space-y-3">
      <GraphControls
        search={search}
        onSearchChange={setSearch}
        groups={groups}
        selectedGroup={groupFilter}
        onGroupChange={setGroupFilter}
        showMode={showMode}
        onShowModeChange={setShowMode}
        zoomPercent={zoomPercent}
        onZoomIn={() => zoomFromCenter(ZOOM_STEP)}
        onZoomOut={() => zoomFromCenter(1 / ZOOM_STEP)}
        onResetZoom={() => setViewport((current) => ({ ...current, scale: 1 }))}
        onFit={() =>
          setViewport(
            fitViewportToGraph(filteredGraph, size.width, size.height),
          )
        }
      />

      <div className="grid gap-3 lg:grid-cols-[1fr_320px]">
        <div
          ref={containerRef}
          className="graph-panel relative h-[360px] overflow-hidden rounded-2xl border border-gray-200 bg-white md:h-[420px]"
        >
          <svg
            width={size.width}
            height={size.height}
            className={dragging ? "cursor-grabbing" : "cursor-grab"}
            onWheel={onWheel}
            onPointerDown={onPointerDown}
            onPointerMove={onPointerMove}
            onPointerUp={endDrag}
            onPointerCancel={endDrag}
            onClick={(event) => {
              const target = event.target as Element;
              if (!target.closest("[data-node='true']")) setSelectedKey(null);
            }}
          >
            <g
              transform={`translate(${viewport.x}, ${viewport.y}) scale(${viewport.scale})`}
            >
              <g>
                {filteredGraph.edges.map((edge, index) => {
                  const source = positionMap.get(edge.source);
                  const target = positionMap.get(edge.target);
                  if (!source || !target) return null;
                  const active = activeKey
                    ? edge.source === activeKey || edge.target === activeKey
                    : false;
                  return (
                    <line
                      key={`${edge.source}-${edge.target}-${edge.field}-${index}`}
                      x1={source.x}
                      y1={source.y}
                      x2={target.x}
                      y2={target.y}
                      stroke={active ? "#2563eb" : "#94a3b8"}
                      strokeOpacity={active ? 0.9 : 0.35}
                      strokeWidth={active ? 2 : 1}
                    />
                  );
                })}
              </g>

              <g>
                {filteredGraph.nodes.map((node) => {
                  const isHovered = hoveredKey === node.key;
                  const isSelected = selectedKey === node.key;
                  const isActive = isHovered || isSelected;
                  const isRelated = activeSet?.has(node.key) ?? false;
                  const opacity = activeSet ? (isRelated ? 1 : 0.25) : 1;
                  const showLabel =
                    isActive ||
                    (!activeSet && viewport.scale >= 1.1) ||
                    (activeSet && isRelated && viewport.scale >= 1.1);

                  return (
                    <g
                      key={node.key}
                      data-node="true"
                      transform={`translate(${node.x}, ${node.y})`}
                      className="cursor-pointer"
                      onPointerEnter={() => setHoveredKey(node.key)}
                      onPointerLeave={() =>
                        setHoveredKey((prev) =>
                          prev === node.key ? null : prev,
                        )
                      }
                      onClick={(event) => {
                        event.stopPropagation();
                        setSelectedKey(node.key);
                      }}
                      opacity={opacity}
                    >
                      <circle
                        r={computeNodeRadius(
                          node,
                          isSelected
                            ? "selected"
                            : isHovered
                              ? "hover"
                              : "base",
                        )}
                        fill={pickGroupColor(node.group)}
                        stroke={isSelected ? "#111827" : "#ffffff"}
                        strokeWidth={isSelected ? 2 : 1.5}
                      />
                      {showLabel ? (
                        <text
                          x={10}
                          y={-10}
                          fontSize={11}
                          fill="#0f172a"
                          stroke="#ffffff"
                          strokeWidth={2}
                          paintOrder="stroke"
                        >
                          {node.name || node.resourceId}
                        </text>
                      ) : null}
                    </g>
                  );
                })}
              </g>
            </g>
          </svg>
        </div>

        <GraphDetailsPanel
          selectedNode={selectedNode}
          edges={filteredGraph.edges}
        />
      </div>
    </div>
  );
}
