import {
  useEffect,
  useMemo,
  useRef,
  useState,
  type KeyboardEvent,
} from "react";
import Sigma from "sigma";
import type {
  EdgeDisplayData,
  MouseCoords,
  NodeDisplayData,
} from "sigma/types";
import {
  buildGraphologyGraph,
  type GraphData,
  type ResourceGraphEdgeAttributes,
  type ResourceGraphNodeAttributes,
  type ResourceGraphologyGraph,
} from "../graph/graphLayout";
import { useI18n } from "../i18n";
import type { GraphViewMode } from "./GraphControls";

type GraphInteractionMode = "cooperative" | "greedy";

type Props = {
  graph: GraphData;
  activeKey: string | null;
  activeSet: Set<string> | null;
  selectedKey: string | null;
  viewMode: GraphViewMode;
  hiddenResourceCount: number;
  hasManualLayout: boolean;
  mode: GraphInteractionMode;
  showExpandButton: boolean;
  showCloseButton: boolean;
  panelClassName: string;
  onExpand: () => void;
  onClose: () => void;
  onSelectNode: (key: string) => void;
  onOpenNode: (key: string) => void;
  onHoverNode: (key: string) => void;
  onClearHover: (key: string) => void;
  onClearSelected: () => void;
  onResetLayout: () => void;
  onNodePositionChange: (
    key: string,
    position: { x: number; y: number },
  ) => void;
};

type Renderer = Sigma<ResourceGraphNodeAttributes, ResourceGraphEdgeAttributes>;

type DisplayState = {
  activeKey: string | null;
  activeSet: Set<string> | null;
  selectedKey: string | null;
  viewMode: GraphViewMode;
  nodeCount: number;
};

type DragBounds = {
  minX: number;
  maxX: number;
  minY: number;
  maxY: number;
};

type DragState = {
  node: string;
  moved: boolean;
  offsetX: number;
  offsetY: number;
  startX: number;
  startY: number;
  bounds: DragBounds;
};

type Callbacks = Pick<
  Props,
  | "onSelectNode"
  | "onOpenNode"
  | "onHoverNode"
  | "onClearHover"
  | "onClearSelected"
  | "onNodePositionChange"
>;

const ZOOM_FACTOR = 1.18;
const CAMERA_DURATION_MS = 160;
const DRAG_START_THRESHOLD_PX = 3;

function clamp(value: number, min: number, max: number) {
  return Math.min(max, Math.max(min, value));
}

function getDragBounds(
  graph: ResourceGraphologyGraph,
  fallbackPadding = 240,
): DragBounds {
  let minX = Infinity;
  let maxX = -Infinity;
  let minY = Infinity;
  let maxY = -Infinity;

  graph.forEachNode((_key, attributes) => {
    if (Number.isFinite(attributes.x)) {
      minX = Math.min(minX, attributes.x);
      maxX = Math.max(maxX, attributes.x);
    }
    if (Number.isFinite(attributes.y)) {
      minY = Math.min(minY, attributes.y);
      maxY = Math.max(maxY, attributes.y);
    }
  });

  if (
    !Number.isFinite(minX) ||
    !Number.isFinite(maxX) ||
    !Number.isFinite(minY) ||
    !Number.isFinite(maxY)
  ) {
    return {
      minX: -fallbackPadding,
      maxX: fallbackPadding,
      minY: -fallbackPadding,
      maxY: fallbackPadding,
    };
  }

  const width = Math.max(1, maxX - minX);
  const height = Math.max(1, maxY - minY);
  const padding = Math.max(fallbackPadding, Math.max(width, height) * 0.18);

  return {
    minX: minX - padding,
    maxX: maxX + padding,
    minY: minY - padding,
    maxY: maxY + padding,
  };
}

function clampDragPosition(
  position: { x: number; y: number },
  bounds: DragBounds,
) {
  return {
    x: clamp(position.x, bounds.minX, bounds.maxX),
    y: clamp(position.y, bounds.minY, bounds.maxY),
  };
}

function dimColor(color: string, amount = 0.22) {
  const hex = color.replace("#", "");
  if (hex.length !== 6) return "#d6dee9";
  const r = parseInt(hex.slice(0, 2), 16);
  const g = parseInt(hex.slice(2, 4), 16);
  const b = parseInt(hex.slice(4, 6), 16);
  const mix = (value: number) =>
    Math.round(value * amount + 245 * (1 - amount))
      .toString(16)
      .padStart(2, "0");
  return `#${mix(r)}${mix(g)}${mix(b)}`;
}

function makeNodeReducer(stateRef: { current: DisplayState }) {
  return (
    key: string,
    data: ResourceGraphNodeAttributes,
  ): Partial<NodeDisplayData> => {
    const state = stateRef.current;
    const isHoveredOrSelected = key === state.activeKey;
    const isSelected = key === state.selectedKey;
    const isRelated = state.activeSet?.has(key) ?? false;
    const isDimmed = Boolean(state.activeSet) && !isRelated;
    const showFocusLabel =
      state.viewMode === "focus" && state.nodeCount <= 24 && isRelated;
    const showLabel =
      isHoveredOrSelected ||
      showFocusLabel ||
      (!state.activeSet && state.viewMode !== "overview" && data.degree >= 2);

    return {
      ...data,
      color: isDimmed ? dimColor(data.color) : data.color,
      forceLabel: showLabel,
      highlighted: isHoveredOrSelected,
      label: showLabel ? data.label : null,
      size: isSelected
        ? data.size + 3
        : isHoveredOrSelected
          ? data.size + 2
          : data.size,
      zIndex: isHoveredOrSelected ? 3 : isRelated ? 2 : 1,
    };
  };
}

function makeEdgeReducer(stateRef: { current: DisplayState }) {
  return (
    _key: string,
    data: ResourceGraphEdgeAttributes,
  ): Partial<EdgeDisplayData> => {
    const state = stateRef.current;
    const isActive =
      state.activeKey === data.source || state.activeKey === data.target;
    const isDimmed = Boolean(state.activeSet) && !isActive;

    return {
      ...data,
      color: isActive ? "#38bdf8" : isDimmed ? "#e2e8f0" : "#94a3b8",
      size: isActive ? 2.2 : isDimmed ? 0.6 : 1.1,
    };
  };
}

function moveCameraWithKeyboard(
  event: KeyboardEvent<HTMLDivElement>,
  renderer: Renderer,
) {
  const camera = renderer.getCamera();
  const state = camera.getState();
  const step = (event.shiftKey ? 0.18 : 0.08) * state.ratio;

  switch (event.key) {
    case "+":
    case "=":
      event.preventDefault();
      void camera.animatedZoom({
        duration: CAMERA_DURATION_MS,
        factor: ZOOM_FACTOR,
      });
      break;
    case "-":
    case "_":
      event.preventDefault();
      void camera.animatedUnzoom({
        duration: CAMERA_DURATION_MS,
        factor: ZOOM_FACTOR,
      });
      break;
    case "0":
      event.preventDefault();
      void camera.animatedReset({ duration: CAMERA_DURATION_MS });
      break;
    case "ArrowLeft":
      event.preventDefault();
      camera.setState({ x: state.x - step });
      break;
    case "ArrowRight":
      event.preventDefault();
      camera.setState({ x: state.x + step });
      break;
    case "ArrowUp":
      event.preventDefault();
      camera.setState({ y: state.y - step });
      break;
    case "ArrowDown":
      event.preventDefault();
      camera.setState({ y: state.y + step });
      break;
  }
}

export function SigmaGraphCanvas({
  graph,
  activeKey,
  activeSet,
  selectedKey,
  viewMode,
  hiddenResourceCount,
  hasManualLayout,
  mode,
  showExpandButton,
  showCloseButton,
  panelClassName,
  onExpand,
  onClose,
  onSelectNode,
  onOpenNode,
  onHoverNode,
  onClearHover,
  onClearSelected,
  onResetLayout,
  onNodePositionChange,
}: Props) {
  const { t } = useI18n();
  const containerRef = useRef<HTMLDivElement | null>(null);
  const rendererRef = useRef<Renderer | null>(null);
  const dragRef = useRef<DragState | null>(null);
  const suppressNextClickRef = useRef(false);
  const suppressClickTimeoutRef = useRef<number | null>(null);
  const [zoomPercent, setZoomPercent] = useState(100);
  const graphology = useMemo(() => buildGraphologyGraph(graph), [graph]);
  const stateRef = useRef<DisplayState>({
    activeKey,
    activeSet,
    selectedKey,
    viewMode,
    nodeCount: graph.nodes.length,
  });
  const callbacksRef = useRef<Callbacks>({
    onSelectNode,
    onOpenNode,
    onHoverNode,
    onClearHover,
    onClearSelected,
    onNodePositionChange,
  });

  stateRef.current = {
    activeKey,
    activeSet,
    selectedKey,
    viewMode,
    nodeCount: graph.nodes.length,
  };
  callbacksRef.current = {
    onSelectNode,
    onOpenNode,
    onHoverNode,
    onClearHover,
    onClearSelected,
    onNodePositionChange,
  };

  useEffect(() => {
    const renderer = rendererRef.current;
    if (!renderer) return;
    renderer.refresh({ schedule: true });
  }, [activeKey, activeSet, graph.nodes.length, selectedKey, viewMode]);

  useEffect(() => {
    const container = containerRef.current;
    if (!container) return;

    const renderer = new Sigma(graphology, container, {
      allowInvalidContainer: true,
      autoCenter: true,
      autoRescale: true,
      defaultEdgeColor: "#94a3b8",
      defaultNodeColor: "#0f172a",
      doubleClickZoomingRatio: 1,
      edgeReducer: makeEdgeReducer(stateRef),
      enableCameraRotation: false,
      hideEdgesOnMove: false,
      hideLabelsOnMove: true,
      labelColor: { color: "#334155" },
      labelDensity: mode === "greedy" ? 0.14 : 0.08,
      labelRenderedSizeThreshold: mode === "greedy" ? 7 : 9,
      labelSize: 11,
      maxCameraRatio: 4,
      minCameraRatio: 0.08,
      nodeReducer: makeNodeReducer(stateRef),
      renderLabels: true,
      stagePadding: mode === "greedy" ? 36 : 24,
      zIndex: true,
    });
    rendererRef.current = renderer;

    const camera = renderer.getCamera();
    const updateZoom = () => {
      const ratio = camera.getState().ratio || 1;
      setZoomPercent(Math.round(100 / ratio));
    };
    updateZoom();
    camera.on("updated", updateZoom);
    let lastContainerSize = {
      height: container.clientHeight,
      width: container.clientWidth,
    };
    let resizeObserverFrame = 0;
    const syncContainerSize = (fitGraph = false) => {
      if (!container.clientWidth || !container.clientHeight) return;
      renderer.resize(true);
      if (fitGraph) {
        camera.setState({ angle: 0, ratio: 1, x: 0.5, y: 0.5 });
        updateZoom();
      }
      renderer.refresh({ schedule: true });
      lastContainerSize = {
        height: container.clientHeight,
        width: container.clientWidth,
      };
    };
    const resizeObserver = new ResizeObserver(() => {
      window.cancelAnimationFrame(resizeObserverFrame);
      resizeObserverFrame = window.requestAnimationFrame(() => {
        const widthDelta = Math.abs(
          container.clientWidth - lastContainerSize.width,
        );
        const heightDelta = Math.abs(
          container.clientHeight - lastContainerSize.height,
        );
        syncContainerSize(widthDelta > 48 || heightDelta > 48);
      });
    });
    resizeObserver.observe(container);

    const clearSuppressedClick = () => {
      suppressNextClickRef.current = false;
      if (suppressClickTimeoutRef.current !== null) {
        window.clearTimeout(suppressClickTimeoutRef.current);
        suppressClickTimeoutRef.current = null;
      }
    };
    const consumeSuppressedClick = () => {
      if (!suppressNextClickRef.current) return false;
      clearSuppressedClick();
      return true;
    };
    const suppressNextClickTemporarily = () => {
      clearSuppressedClick();
      suppressNextClickRef.current = true;
      suppressClickTimeoutRef.current = window.setTimeout(
        clearSuppressedClick,
        180,
      );
    };

    const onClickNode = ({ node }: { node: string }) => {
      if (consumeSuppressedClick()) return;
      callbacksRef.current.onSelectNode(node);
    };
    const onDoubleClickNode = ({
      node,
      event,
      preventSigmaDefault,
    }: {
      node: string;
      event: MouseCoords;
      preventSigmaDefault: () => void;
    }) => {
      preventSigmaDefault();
      event.original.preventDefault();
      callbacksRef.current.onOpenNode(node);
    };
    const onDownNode = ({
      node,
      event,
      preventSigmaDefault,
    }: {
      node: string;
      event: MouseCoords;
      preventSigmaDefault: () => void;
    }) => {
      preventSigmaDefault();
      event.original.preventDefault();
      event.original.stopPropagation();
      renderer.getCamera().disable();
      const pointer = renderer.viewportToGraph({ x: event.x, y: event.y });
      const attributes = graphology.getNodeAttributes(node);
      dragRef.current = {
        node,
        moved: false,
        offsetX: attributes.x - pointer.x,
        offsetY: attributes.y - pointer.y,
        startX: event.x,
        startY: event.y,
        bounds: getDragBounds(graphology),
      };
      callbacksRef.current.onHoverNode(node);
    };
    const onEnterNode = ({ node }: { node: string }) => {
      callbacksRef.current.onHoverNode(node);
    };
    const onLeaveNode = ({ node }: { node: string }) => {
      callbacksRef.current.onClearHover(node);
    };
    const onClickStage = () => {
      if (consumeSuppressedClick()) return;
      callbacksRef.current.onClearSelected();
    };

    renderer.on("clickNode", onClickNode);
    renderer.on("doubleClickNode", onDoubleClickNode);
    renderer.on("downNode", onDownNode);
    renderer.on("enterNode", onEnterNode);
    renderer.on("leaveNode", onLeaveNode);
    renderer.on("clickStage", onClickStage);

    const mouseCaptor = renderer.getMouseCaptor();
    const onMouseMoveBody = (event: MouseCoords) => {
      const dragging = dragRef.current;
      if (!dragging) return;
      event.preventSigmaDefault();
      event.original.preventDefault();
      event.original.stopPropagation();
      const movedPixels = Math.hypot(
        event.x - dragging.startX,
        event.y - dragging.startY,
      );
      if (movedPixels < DRAG_START_THRESHOLD_PX) return;
      const pointer = renderer.viewportToGraph({ x: event.x, y: event.y });
      const position = clampDragPosition(
        {
          x: pointer.x + dragging.offsetX,
          y: pointer.y + dragging.offsetY,
        },
        dragging.bounds,
      );
      graphology.setNodeAttribute(dragging.node, "x", position.x);
      graphology.setNodeAttribute(dragging.node, "y", position.y);
      dragging.moved = true;
      renderer.refresh({ schedule: true });
    };
    const onMouseUp = () => {
      const dragging = dragRef.current;
      renderer.getCamera().enable();
      if (!dragging) return;
      if (dragging.moved) {
        const attributes = graphology.getNodeAttributes(dragging.node);
        const node = dragging.node;
        const position = { x: attributes.x, y: attributes.y };
        suppressNextClickTemporarily();
        window.setTimeout(() => {
          callbacksRef.current.onNodePositionChange(node, position);
        }, 0);
      }
      dragRef.current = null;
    };
    mouseCaptor.on("mousemovebody", onMouseMoveBody);
    mouseCaptor.on("mouseup", onMouseUp);

    const resizeFrame = window.requestAnimationFrame(() =>
      syncContainerSize(true),
    );
    const resizeTimeout = window.setTimeout(() => syncContainerSize(true), 120);

    return () => {
      window.cancelAnimationFrame(resizeFrame);
      window.cancelAnimationFrame(resizeObserverFrame);
      window.clearTimeout(resizeTimeout);
      resizeObserver.disconnect();
      camera.off("updated", updateZoom);
      renderer.kill();
      rendererRef.current = null;
      dragRef.current = null;
      clearSuppressedClick();
    };
  }, [graphology, mode]);

  const zoomIn = () => {
    void rendererRef.current
      ?.getCamera()
      .animatedZoom({ duration: CAMERA_DURATION_MS, factor: ZOOM_FACTOR });
  };

  const zoomOut = () => {
    void rendererRef.current
      ?.getCamera()
      .animatedUnzoom({ duration: CAMERA_DURATION_MS, factor: ZOOM_FACTOR });
  };

  const fitGraph = () => {
    void rendererRef.current
      ?.getCamera()
      .animatedReset({ duration: CAMERA_DURATION_MS });
  };

  return (
    <div
      className={panelClassName}
      onKeyDown={(event) => {
        const renderer = rendererRef.current;
        if (renderer) moveCameraWithKeyboard(event, renderer);
      }}
      role="img"
      aria-label={t("Resource Relation Graph")}
      tabIndex={0}
    >
      <div className="pointer-events-none absolute left-4 top-4 z-10 max-w-[calc(100%-7.25rem)] rounded-xl border border-gray-200 bg-white/90 px-3 py-1.5 text-xs font-medium leading-relaxed text-gray-700 shadow-lg backdrop-blur sm:max-w-none sm:rounded-full sm:py-1 sm:leading-normal">
        {graph.nodes.length} {t("Resources")} · {graph.edges.length}{" "}
        {t("Relations")}
        {hiddenResourceCount > 0 ? (
          <>
            {" "}
            · {hiddenResourceCount} {t("hidden")}
          </>
        ) : null}
      </div>

      {showExpandButton ? (
        <div className="absolute right-3 top-3 z-20">
          <button
            type="button"
            className="min-h-11 rounded-lg border border-gray-200 bg-white/95 px-3 py-2 text-xs font-medium text-gray-700 shadow-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
            onClick={onExpand}
          >
            {t("Expand")}
          </button>
        </div>
      ) : null}

      {showCloseButton ? (
        <div className="absolute right-3 top-3 z-20">
          <button
            type="button"
            className="min-h-11 rounded-lg border border-gray-200 bg-white/95 px-3 py-2 text-xs font-medium text-gray-700 shadow-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
            onClick={onClose}
          >
            {t("Close")}
          </button>
        </div>
      ) : null}

      <div className="absolute bottom-3 right-3 z-20 flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white/95 shadow-lg backdrop-blur">
        <button
          type="button"
          onClick={zoomIn}
          className="min-h-11 min-w-11 border-b border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500/30"
          aria-label={t("Zoom in")}
        >
          +
        </button>
        <button
          type="button"
          onClick={zoomOut}
          className="min-h-11 min-w-11 border-b border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500/30"
          aria-label={t("Zoom out")}
        >
          -
        </button>
        <button
          type="button"
          onClick={fitGraph}
          className="min-h-11 min-w-11 border-b border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500/30"
        >
          {t("Fit")}
        </button>
        <button
          type="button"
          onClick={onResetLayout}
          disabled={!hasManualLayout}
          className="min-h-11 min-w-11 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500/30 disabled:cursor-not-allowed disabled:opacity-45"
          aria-label={t("Reset Layout")}
          title={t("Reset Layout")}
        >
          {t("Reset")}
        </button>
        {mode === "greedy" ? (
          <div className="border-t border-gray-200 px-3 py-1.5 text-center text-[11px] font-medium text-gray-500">
            {zoomPercent}%
          </div>
        ) : null}
      </div>

      <div ref={containerRef} className="absolute inset-0" />
      <div className="sr-only" aria-label={t("Resource Relation Graph")}>
        {graph.nodes.map((node) => (
          <button
            key={node.key}
            type="button"
            onFocus={() => onHoverNode(node.key)}
            onBlur={() => onClearHover(node.key)}
            onClick={() => onSelectNode(node.key)}
          >
            {t("Select Resource")} {node.typeTitle} /{" "}
            {node.name || node.resourceId}
          </button>
        ))}
      </div>
    </div>
  );
}
