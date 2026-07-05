import { MultiDirectedGraph } from "graphology";
import circular from "graphology-layout/circular";
import forceAtlas2 from "graphology-layout-forceatlas2";
import noverlap from "graphology-layout-noverlap";
import type { Resource, ResourceType } from "../types";
import { hashString } from "./hash";
import { normalizeGroupName, pickTypeColor } from "./graphColors";

export type GraphNode = {
  key: string;
  typeId: string;
  typeTitle: string;
  resourceId: string;
  name: string;
  group: string;
  degree: number;
  x: number;
  y: number;
};

export type GraphEdge = {
  source: string;
  target: string;
  field: string;
};

export type GraphData = {
  nodes: GraphNode[];
  edges: GraphEdge[];
};

export type ResourceGraphNodeAttributes = GraphNode & {
  label: string;
  color: string;
  size: number;
  forceLabel: boolean;
  zIndex: number;
};

export type ResourceGraphEdgeAttributes = GraphEdge & {
  label: string;
  color: string;
  size: number;
};

export type ResourceGraphologyGraph = MultiDirectedGraph<
  ResourceGraphNodeAttributes,
  ResourceGraphEdgeAttributes
>;

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

export function buildGraphData(
  types: ResourceType[],
  resourcesByType: Record<string, Resource[]>,
): GraphData {
  const nodes: GraphNode[] = [];
  const edges: GraphEdge[] = [];
  const seenNodes = new Set<string>();

  const sortedTypes = [...types].sort((a, b) => {
    const groupCompare = normalizeGroupName(a.group).localeCompare(
      normalizeGroupName(b.group),
    );
    if (groupCompare !== 0) return groupCompare;
    return (a.title || a.id).localeCompare(b.title || b.id);
  });

  for (const type of sortedTypes) {
    const resources = [...(resourcesByType[type.id] ?? [])].sort((a, b) => {
      const nameCompare = (a.name || "").localeCompare(b.name || "");
      if (nameCompare !== 0) return nameCompare;
      return a.id.localeCompare(b.id);
    });

    for (const resource of resources) {
      const key = nodeKey(type.id, resource.id);
      if (seenNodes.has(key)) continue;
      seenNodes.add(key);
      nodes.push({
        key,
        typeId: type.id,
        typeTitle: type.title || type.id,
        resourceId: resource.id,
        name: resource.name || resource.id,
        group: normalizeGroupName(type.group),
        degree: 0,
        x: (hashString(key) % 600) - 300,
        y: (hashString(`${key}:y`) % 400) - 200,
      });
    }
  }

  const edgeKeys = new Set<string>();
  for (const type of sortedTypes) {
    const resources = resourcesByType[type.id] ?? [];

    for (const resource of resources) {
      const sourceKey = nodeKey(type.id, resource.id);

      for (const reference of type.references ?? []) {
        const rawValue = resource.spec[reference.field];
        if (rawValue == null) continue;
        if (reference.multiple && Array.isArray(rawValue)) {
          for (const item of rawValue) {
            const targetId = toTargetId(item);
            if (!targetId) continue;
            const targetKey = nodeKey(reference.target_type, targetId);
            if (!seenNodes.has(targetKey)) continue;
            const edgeKey = `${sourceKey}|${targetKey}|${reference.field}`;
            if (edgeKeys.has(edgeKey)) continue;
            edgeKeys.add(edgeKey);
            edges.push({
              source: sourceKey,
              target: targetKey,
              field: reference.field,
            });
          }
          continue;
        }

        const targetId = toTargetId(rawValue);
        if (!targetId) continue;
        const targetKey = nodeKey(reference.target_type, targetId);
        if (!seenNodes.has(targetKey)) continue;
        const edgeKey = `${sourceKey}|${targetKey}|${reference.field}`;
        if (edgeKeys.has(edgeKey)) continue;
        edgeKeys.add(edgeKey);
        edges.push({
          source: sourceKey,
          target: targetKey,
          field: reference.field,
        });
      }

      for (const [targetType, rawDependency] of Object.entries(
        resource.dependencies ?? {},
      )) {
        if (Array.isArray(rawDependency)) {
          for (const item of rawDependency) {
            const targetId = toTargetId(item);
            if (!targetId) continue;
            const targetKey = nodeKey(targetType, targetId);
            if (!seenNodes.has(targetKey)) continue;
            const edgeKey = `${sourceKey}|${targetKey}|dependencies.${targetType}`;
            if (edgeKeys.has(edgeKey)) continue;
            edgeKeys.add(edgeKey);
            edges.push({
              source: sourceKey,
              target: targetKey,
              field: `dependencies.${targetType}`,
            });
          }
          continue;
        }

        const targetId = toTargetId(rawDependency);
        if (!targetId) continue;
        const targetKey = nodeKey(targetType, targetId);
        if (!seenNodes.has(targetKey)) continue;
        const edgeKey = `${sourceKey}|${targetKey}|dependencies.${targetType}`;
        if (edgeKeys.has(edgeKey)) continue;
        edgeKeys.add(edgeKey);
        edges.push({
          source: sourceKey,
          target: targetKey,
          field: `dependencies.${targetType}`,
        });
      }
    }
  }

  const degreeMap = new Map<string, number>(nodes.map((node) => [node.key, 0]));
  for (const edge of edges) {
    degreeMap.set(edge.source, (degreeMap.get(edge.source) ?? 0) + 1);
    degreeMap.set(edge.target, (degreeMap.get(edge.target) ?? 0) + 1);
  }
  for (const node of nodes) {
    node.degree = degreeMap.get(node.key) ?? 0;
  }

  return { nodes, edges };
}

export function computeNodeRadius(
  node: GraphNode,
  state: "base" | "hover" | "selected" = "base",
): number {
  const base = Math.min(12, 5 + node.degree * 1.2);
  if (state === "selected") return base + 3;
  if (state === "hover") return base + 2;
  return base;
}

export function buildGraphologyGraph(
  graph: GraphData,
): ResourceGraphologyGraph {
  const result = new MultiDirectedGraph<
    ResourceGraphNodeAttributes,
    ResourceGraphEdgeAttributes
  >({ allowSelfLoops: false });

  for (const node of graph.nodes) {
    const size = computeNodeRadius(node);
    result.addNode(node.key, {
      ...node,
      label: node.name || node.resourceId,
      color: pickTypeColor(node.typeId),
      size,
      forceLabel: false,
      zIndex: Math.max(1, node.degree),
    });
  }

  graph.edges.forEach((edge, index) => {
    const edgeKey = `${edge.source}|${edge.target}|${edge.field}|${index}`;
    if (!result.hasNode(edge.source) || !result.hasNode(edge.target)) return;
    if (edge.source === edge.target) return;
    result.addDirectedEdgeWithKey(edgeKey, edge.source, edge.target, {
      ...edge,
      label: edge.field,
      color: "#94a3b8",
      size: 1.1,
    });
  });

  return result;
}

const TYPE_ATTRACTION_PASSES = 9;
const TYPE_ATTRACTION_STRENGTH = 0.055;

function finitePosition(value: number, fallback: number) {
  return Number.isFinite(value) ? value : fallback;
}

function applyResourceTypeAttraction(graphology: ResourceGraphologyGraph) {
  const typeIds = [
    ...new Set(
      graphology
        .nodes()
        .map((key) => graphology.getNodeAttribute(key, "typeId")),
    ),
  ].sort();

  if (typeIds.length <= 1 || graphology.order <= 3) return;

  const centerRadius = Math.max(
    180,
    Math.min(560, Math.sqrt(graphology.order) * 34 + typeIds.length * 14),
  );
  const centers = new Map<string, { x: number; y: number }>();
  typeIds.forEach((typeId, index) => {
    const angle = (Math.PI * 2 * index) / typeIds.length - Math.PI / 2;
    centers.set(typeId, {
      x: Math.cos(angle) * centerRadius,
      y: Math.sin(angle) * centerRadius,
    });
  });

  for (let pass = 0; pass < TYPE_ATTRACTION_PASSES; pass += 1) {
    graphology.forEachNode((_key, attributes) => {
      const center = centers.get(attributes.typeId);
      if (!center) return;
      const strength =
        attributes.degree === 0
          ? TYPE_ATTRACTION_STRENGTH * 0.75
          : TYPE_ATTRACTION_STRENGTH;
      const x = finitePosition(attributes.x, center.x);
      const y = finitePosition(attributes.y, center.y);
      graphology.mergeNodeAttributes(_key, {
        x: x + (center.x - x) * strength,
        y: y + (center.y - y) * strength,
      });
    });
  }
}

export function computeLayout(graph: GraphData): GraphData {
  const graphology = buildGraphologyGraph(graph);

  if (graphology.order > 1) {
    if (graphology.order <= 2) {
      circular.assign(graphology, { scale: 120 });
    } else {
      forceAtlas2.assign(graphology, {
        iterations: graphology.order > 120 ? 90 : 140,
        settings: {
          ...forceAtlas2.inferSettings(graphology),
          adjustSizes: true,
          barnesHutOptimize: graphology.order > 80,
          gravity: 1.2,
          scalingRatio: graphology.order > 80 ? 8 : 14,
          slowDown: 5,
        },
      });
      applyResourceTypeAttraction(graphology);
      noverlap.assign(graphology, {
        maxIterations: 80,
        settings: {
          expansion: 1.08,
          margin: 8,
          ratio: 1.25,
        },
      });
    }
  }

  return {
    nodes: graph.nodes.map((node) => {
      const attributes = graphology.getNodeAttributes(node.key);
      return {
        ...node,
        x: finitePosition(attributes.x, node.x),
        y: finitePosition(attributes.y, node.y),
      };
    }),
    edges: graph.edges.map((edge) => ({ ...edge })),
  };
}
