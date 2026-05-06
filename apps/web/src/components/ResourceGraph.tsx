import { useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { deriveDisplayLabel, type Resource, type ResourceType } from "../types";
import { TextInput } from "./TextInput";
import { SelectInput } from "./SelectInput";

type GraphNode = {
  key: string;
  typeId: string;
  typeTitle: string;
  resourceId: string;
  name: string;
  columnKey: string;
};

type GraphEdge = {
  source: string;
  target: string;
  field: string;
};

type Props = {
  types: ResourceType[];
  resourcesByType: Record<string, Resource[]>;
  compact?: boolean;
};

function nodeKey(typeId: string, resourceId: string): string {
  return `${typeId}/${resourceId}`;
}

function toTargetId(value: unknown): string | null {
  if (typeof value === "string") return value;
  if (value && typeof value === "object") {
    const row = value as Record<string, unknown>;
    if (typeof row.resource_id === "string") return row.resource_id;
    if (typeof row.id === "string") return row.id;
  }
  return null;
}

function buildGraph(types: ResourceType[], resourcesByType: Record<string, Resource[]>) {
  const typeMap = new Map(types.map((t) => [t.id, t]));
  const groupedTypes = [...types].sort((a, b) => {
    const ag = (a.group || "Other").localeCompare(b.group || "Other");
    if (ag !== 0) return ag;
    return (a.title || a.id).localeCompare(b.title || b.id);
  });

  const nodes: GraphNode[] = [];
  const edges: GraphEdge[] = [];
  const seen = new Set<string>();

  for (const type of groupedTypes) {
    const resources = [...(resourcesByType[type.id] ?? [])].sort((a, b) => {
      const an = (a.metadata.name || "").localeCompare(b.metadata.name || "");
      if (an !== 0) return an;
      return a.metadata.id.localeCompare(b.metadata.id);
    });
    for (const resource of resources) {
      const key = nodeKey(type.id, resource.metadata.id);
      if (seen.has(key)) continue;
      seen.add(key);
      nodes.push({
        key,
        typeId: type.id,
        typeTitle: type.title || type.id,
        resourceId: resource.metadata.id,
        name: resource.metadata.name || resource.metadata.id,
        columnKey: `${type.group || "Other"} / ${type.title || type.id}`,
      });
    }
  }

  const hasNode = (key: string) => seen.has(key);
  const edgeSet = new Set<string>();
  for (const type of types) {
    const resources = resourcesByType[type.id] ?? [];
    for (const resource of resources) {
      const sourceKey = nodeKey(type.id, resource.metadata.id);
      for (const reference of type.references ?? []) {
        const raw = resource.spec[reference.field];
        if (raw == null) continue;
        if (reference.multiple && Array.isArray(raw)) {
          for (const item of raw) {
            const targetId = toTargetId(item);
            if (!targetId) continue;
            const targetKey = nodeKey(reference.target_type, targetId);
            if (!hasNode(targetKey)) continue;
            const edgeKey = `${sourceKey}|${targetKey}|${reference.field}`;
            if (!edgeSet.has(edgeKey)) {
              edgeSet.add(edgeKey);
              edges.push({ source: sourceKey, target: targetKey, field: reference.field });
            }
          }
        } else {
          const targetId = toTargetId(raw);
          if (!targetId) continue;
          const targetKey = nodeKey(reference.target_type, targetId);
          if (!hasNode(targetKey)) continue;
          const edgeKey = `${sourceKey}|${targetKey}|${reference.field}`;
          if (!edgeSet.has(edgeKey)) {
            edgeSet.add(edgeKey);
            edges.push({ source: sourceKey, target: targetKey, field: reference.field });
          }
        }
      }
    }
  }

  return { nodes, edges };
}

export function ResourceGraph({ types, resourcesByType, compact = false }: Props) {
  const [query, setQuery] = useState("");
  const [mode, setMode] = useState<"all" | "connected" | "isolated">("all");
  const [selectedKey, setSelectedKey] = useState<string | null>(null);

  const graph = useMemo(() => buildGraph(types, resourcesByType), [types, resourcesByType]);

  const degree = useMemo(() => {
    const map = new Map<string, number>();
    for (const node of graph.nodes) map.set(node.key, 0);
    for (const edge of graph.edges) {
      map.set(edge.source, (map.get(edge.source) ?? 0) + 1);
      map.set(edge.target, (map.get(edge.target) ?? 0) + 1);
    }
    return map;
  }, [graph]);

  const filteredNodes = useMemo(() => {
    const q = query.trim().toLowerCase();
    return graph.nodes.filter((node) => {
      if (mode === "connected" && (degree.get(node.key) ?? 0) === 0) return false;
      if (mode === "isolated" && (degree.get(node.key) ?? 0) > 0) return false;
      if (!q) return true;
      return [node.typeTitle, node.resourceId, node.name].join(" ").toLowerCase().includes(q);
    });
  }, [degree, graph.nodes, mode, query]);

  const visibleNodeSet = new Set(filteredNodes.map((n) => n.key));
  const visibleEdges = graph.edges.filter(
    (edge) => visibleNodeSet.has(edge.source) && visibleNodeSet.has(edge.target),
  );

  const columns = useMemo(() => {
    const map = new Map<string, GraphNode[]>();
    for (const node of filteredNodes) {
      if (!map.has(node.columnKey)) map.set(node.columnKey, []);
      map.get(node.columnKey)!.push(node);
    }
    return [...map.entries()].map(([key, nodes]) => ({ key, nodes }));
  }, [filteredNodes]);

  const pos = new Map<string, { x: number; y: number }>();
  const columnWidth = 220;
  const columnGap = 80;
  const rowHeight = 86;
  const startX = 20;
  const startY = 28;
  columns.forEach((column, colIndex) => {
    column.nodes.forEach((node, rowIndex) => {
      pos.set(node.key, {
        x: startX + colIndex * (columnWidth + columnGap),
        y: startY + rowIndex * rowHeight,
      });
    });
  });

  const graphHeight = compact ? 360 : 420;
  const canvasWidth = Math.max(980, startX + columns.length * (columnWidth + columnGap));
  const canvasHeight = Math.max(graphHeight, startY + Math.max(...columns.map((c) => c.nodes.length), 1) * rowHeight + 30);

  const selectedNode = filteredNodes.find((n) => n.key === selectedKey) ?? null;
  const selectedEdges = selectedNode
    ? visibleEdges.filter((edge) => edge.source === selectedNode.key || edge.target === selectedNode.key)
    : [];

  if (graph.nodes.length === 0) {
    return (
      <div className="rounded-2xl border border-gray-200 bg-white p-6 text-sm text-gray-600">
        <p className="font-medium text-gray-900">No resources</p>
        <p className="mt-1">Create Resource Types and Resources to populate the graph.</p>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      <div className="grid grid-cols-1 gap-3 md:grid-cols-[1fr_220px]">
        <label className="text-sm text-gray-700">
          Search resources
          <TextInput value={query} onChange={(e) => setQuery(e.target.value)} />
        </label>
        <label className="text-sm text-gray-700">
          Show
          <SelectInput value={mode} onChange={(e) => setMode(e.target.value as typeof mode)}>
            <option value="all">All</option>
            <option value="connected">Connected only</option>
            <option value="isolated">Isolated only</option>
          </SelectInput>
        </label>
      </div>

      <div className="grid gap-3 lg:grid-cols-[1fr_300px]">
        <div className="overflow-auto rounded-2xl border border-gray-200 bg-slate-50" style={{ height: graphHeight }}>
          <div style={{ width: canvasWidth, height: canvasHeight, position: "relative" }}>
            <svg width={canvasWidth} height={canvasHeight} className="absolute inset-0">
              {visibleEdges.map((edge, idx) => {
                const source = pos.get(edge.source);
                const target = pos.get(edge.target);
                if (!source || !target) return null;
                const sx = source.x + columnWidth;
                const sy = source.y + 32;
                const tx = target.x;
                const ty = target.y + 32;
                const mx = (sx + tx) / 2;
                const active = selectedKey ? edge.source === selectedKey || edge.target === selectedKey : false;
                return (
                  <g key={`${edge.source}-${edge.target}-${idx}`}>
                    <path
                      d={`M ${sx} ${sy} C ${mx} ${sy}, ${mx} ${ty}, ${tx} ${ty}`}
                      fill="none"
                      stroke={active ? "#2563eb" : "#94a3b8"}
                      strokeWidth={active ? 2 : 1.5}
                    />
                    <polygon points={`${tx - 8},${ty - 4} ${tx - 8},${ty + 4} ${tx},${ty}`} fill={active ? "#2563eb" : "#94a3b8"} />
                  </g>
                );
              })}
            </svg>

            {filteredNodes.map((node) => {
              const p = pos.get(node.key);
              if (!p) return null;
              const active = selectedKey === node.key;
              return (
                <button
                  key={node.key}
                  onClick={() => setSelectedKey(node.key)}
                  className={`absolute rounded-xl border px-3 py-2 text-left shadow-sm ${
                    active ? "border-blue-500 bg-blue-50" : "border-gray-200 bg-white hover:border-gray-300"
                  }`}
                  style={{ left: p.x, top: p.y, width: columnWidth }}
                >
                  <p className="text-[11px] font-semibold uppercase tracking-wide text-gray-500">{node.typeTitle}</p>
                  <p className="text-sm font-semibold text-gray-900">{node.name}</p>
                  <p className="text-xs text-gray-600">{node.resourceId}</p>
                </button>
              );
            })}
          </div>
        </div>

        <aside className="rounded-2xl border border-gray-200 bg-white p-4">
          <h3 className="text-xs font-semibold uppercase tracking-wide text-gray-500">Selected resource</h3>
          {!selectedNode ? (
            <p className="mt-3 text-sm text-gray-600">Select a node to inspect relations.</p>
          ) : (
            <div className="mt-3 space-y-3 text-sm">
              <div>
                <p><span className="font-medium">Type:</span> {selectedNode.typeTitle}</p>
                <p><span className="font-medium">Name:</span> {selectedNode.name}</p>
                <p><span className="font-medium">ID:</span> {selectedNode.resourceId}</p>
              </div>
              <div>
                <p className="font-medium text-gray-900">Relations</p>
                {selectedEdges.length === 0 ? (
                  <p className="text-gray-600">No relations.</p>
                ) : (
                  <ul className="mt-1 space-y-1 text-xs text-gray-700">
                    {selectedEdges.map((edge, index) => {
                      const other = edge.source === selectedNode.key ? edge.target : edge.source;
                      return (
                        <li key={`${other}-${index}`}>{deriveDisplayLabel(edge.field)}: {other}</li>
                      );
                    })}
                  </ul>
                )}
              </div>
              <Link
                to={`/resources/${selectedNode.typeId}/${selectedNode.resourceId}`}
                className="inline-block text-sm font-medium text-blue-700 hover:text-blue-800"
              >
                Open Resource
              </Link>
            </div>
          )}
        </aside>
      </div>
    </div>
  );
}
