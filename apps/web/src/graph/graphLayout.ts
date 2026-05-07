import {
  forceCenter,
  forceCollide,
  forceLink,
  forceManyBody,
  forceSimulation,
} from "d3-force";
import type { Resource, ResourceType } from "../types";
import { hashString } from "./hash";
import { normalizeGroupName } from "./graphColors";

export type GraphViewport = {
  x: number;
  y: number;
  scale: number;
};

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

export const MIN_SCALE = 0.25;
export const MAX_SCALE = 3.0;

function clamp(value: number, min: number, max: number): number {
  return Math.min(max, Math.max(min, value));
}

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
      const nameCompare = (a.metadata.name || "").localeCompare(
        b.metadata.name || "",
      );
      if (nameCompare !== 0) return nameCompare;
      return a.metadata.id.localeCompare(b.metadata.id);
    });

    for (const resource of resources) {
      const key = nodeKey(type.id, resource.metadata.id);
      if (seenNodes.has(key)) continue;
      seenNodes.add(key);
      nodes.push({
        key,
        typeId: type.id,
        typeTitle: type.title || type.id,
        resourceId: resource.metadata.id,
        name: resource.metadata.name || resource.metadata.id,
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
      const sourceKey = nodeKey(type.id, resource.metadata.id);

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

export function computeLayout(graph: GraphData): GraphData {
  const nodes = graph.nodes.map((node) => ({ ...node }));
  const links = graph.edges.map((edge) => ({ ...edge }));

  const simulation = forceSimulation(nodes)
    .force(
      "link",
      forceLink(links)
        .id((node) => (node as GraphNode).key)
        .distance(90)
        .strength(0.35),
    )
    .force("charge", forceManyBody().strength(-220))
    .force("center", forceCenter(0, 0))
    .force(
      "collision",
      forceCollide<GraphNode>().radius((node) => computeNodeRadius(node) + 8),
    );

  for (let i = 0; i < 180; i += 1) simulation.tick();
  simulation.stop();

  return { nodes, edges: links };
}

export function fitViewportToGraph(
  graph: GraphData,
  width: number,
  height: number,
  padding = 48,
): GraphViewport {
  if (!graph.nodes.length) return { x: 0, y: 0, scale: 1 };

  const minX = Math.min(...graph.nodes.map((n) => n.x));
  const maxX = Math.max(...graph.nodes.map((n) => n.x));
  const minY = Math.min(...graph.nodes.map((n) => n.y));
  const maxY = Math.max(...graph.nodes.map((n) => n.y));

  const graphWidth = Math.max(1, maxX - minX);
  const graphHeight = Math.max(1, maxY - minY);

  const innerWidth = Math.max(1, width - padding * 2);
  const innerHeight = Math.max(1, height - padding * 2);
  const scale = clamp(
    Math.min(innerWidth / graphWidth, innerHeight / graphHeight),
    MIN_SCALE,
    MAX_SCALE,
  );

  const centerX = (minX + maxX) / 2;
  const centerY = (minY + maxY) / 2;

  return {
    x: width / 2 - centerX * scale,
    y: height / 2 - centerY * scale,
    scale,
  };
}

export function clampScale(scale: number): number {
  return clamp(scale, MIN_SCALE, MAX_SCALE);
}
