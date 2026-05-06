import { useEffect, useMemo, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { api } from '../api/client'
import { ErrorBanner } from '../components/ErrorBanner'
import { PageHeader } from '../components/PageHeader'
import type { Resource, ResourceType } from '../types'

type GraphNode = {
  id: string
  type: string
  resourceId: string
  name: string
}

type GraphEdge = {
  source: string
  target: string
  reason: string
}

type SimNode = GraphNode & {
  x: number
  y: number
  vx: number
  vy: number
}

const NODE_RADIUS = 7
const TARGET_LINK = 110

function nodeKey(type: string, id: string): string {
  return `${type}/${id}`
}

function toTargetKey(type: string, value: unknown): string | null {
  if (typeof value === 'string') return nodeKey(type, value)
  if (value && typeof value === 'object') {
    const r = value as Record<string, unknown>
    if (typeof r.type === 'string' && typeof r.id === 'string') return nodeKey(r.type, r.id)
    if (typeof r.resource_type === 'string' && typeof r.resource_id === 'string') return nodeKey(r.resource_type, r.resource_id)
    if (typeof r.type === 'string' && typeof r.resource_id === 'string') return nodeKey(r.type, r.resource_id)
  }
  return null
}

function buildGraph(types: ResourceType[], resourcesByType: Record<string, Resource[]>) {
  const nodes: GraphNode[] = []
  const nodeSet = new Set<string>()
  const edgesMap = new Map<string, GraphEdge>()

  for (const [type, resources] of Object.entries(resourcesByType)) {
    for (const resource of resources) {
      const key = nodeKey(type, resource.metadata.id)
      if (nodeSet.has(key)) continue
      nodeSet.add(key)
      nodes.push({
        id: key,
        type,
        resourceId: resource.metadata.id,
        name: resource.metadata.name || resource.metadata.id
      })
    }
  }

  const typeMap = new Map(types.map((t) => [t.id, t]))
  const hasNode = (id: string): boolean => nodeSet.has(id)
  const addEdge = (source: string, target: string, reason: string) => {
    if (source === target) return
    if (!hasNode(source) || !hasNode(target)) return
    const key = `${source}->${target}:${reason}`
    if (!edgesMap.has(key)) edgesMap.set(key, { source, target, reason })
  }

  for (const [type, resources] of Object.entries(resourcesByType)) {
    const resourceType = typeMap.get(type)
    const refs = resourceType?.references ?? []
    for (const resource of resources) {
      const sourceKey = nodeKey(type, resource.metadata.id)

      for (const ref of refs) {
        const raw = resource.spec[ref.field]
        if (raw == null) continue
        if (ref.multiple && Array.isArray(raw)) {
          for (const item of raw) {
            const targetKey = toTargetKey(ref.target_type, item)
            if (targetKey) addEdge(sourceKey, targetKey, `field:${ref.field}`)
          }
        } else {
          const targetKey = toTargetKey(ref.target_type, raw)
          if (targetKey) addEdge(sourceKey, targetKey, `field:${ref.field}`)
        }
      }

      for (const [targetType, raw] of Object.entries(resource.dependencies ?? {})) {
        if (Array.isArray(raw)) {
          for (const item of raw) {
            const targetKey = toTargetKey(targetType, item)
            if (targetKey) addEdge(sourceKey, targetKey, 'dependencies')
          }
        } else {
          const targetKey = toTargetKey(targetType, raw)
          if (targetKey) addEdge(sourceKey, targetKey, 'dependencies')
        }
      }
    }
  }

  return { nodes, edges: Array.from(edgesMap.values()) }
}

export function GraphViewPage() {
  const [types, setTypes] = useState<ResourceType[]>([])
  const [resourcesByType, setResourcesByType] = useState<Record<string, Resource[]>>({})
  const [error, setError] = useState<string | null>(null)
  const [loading, setLoading] = useState(true)
  const [selected, setSelected] = useState<string | null>(null)
  const containerRef = useRef<HTMLDivElement | null>(null)
  const [size, setSize] = useState({ width: 900, height: 620 })

  useEffect(() => {
    let alive = true
    setLoading(true)
    api
      .listResourceTypes()
      .then(async (rt) => {
        const entries = await Promise.all(rt.map(async (t) => [t.id, await api.listResources(t.id)] as const))
        if (!alive) return
        setTypes(rt)
        setResourcesByType(Object.fromEntries(entries))
      })
      .catch((e: Error) => setError(e.message))
      .finally(() => {
        if (alive) setLoading(false)
      })
    return () => {
      alive = false
    }
  }, [])

  useEffect(() => {
    if (!containerRef.current) return
    const obs = new ResizeObserver((entries) => {
      const rect = entries[0]?.contentRect
      if (!rect) return
      setSize({
        width: Math.max(480, Math.floor(rect.width)),
        height: Math.max(420, Math.floor(rect.height))
      })
    })
    obs.observe(containerRef.current)
    return () => obs.disconnect()
  }, [])

  const { nodes, edges } = useMemo(() => buildGraph(types, resourcesByType), [types, resourcesByType])

  const positions = useMemo(() => {
    const width = size.width
    const height = size.height
    const simNodes: SimNode[] = nodes.map((n, i) => ({
      ...n,
      x: ((i * 67) % (width - 120)) + 60,
      y: ((i * 41) % (height - 120)) + 60,
      vx: 0,
      vy: 0
    }))
    const index = new Map(simNodes.map((n) => [n.id, n]))

    for (let step = 0; step < 260; step += 1) {
      for (let i = 0; i < simNodes.length; i += 1) {
        for (let j = i + 1; j < simNodes.length; j += 1) {
          const a = simNodes[i]
          const b = simNodes[j]
          const dx = b.x - a.x
          const dy = b.y - a.y
          const d2 = dx * dx + dy * dy + 0.01
          const repulse = 1800 / d2
          a.vx -= (dx / Math.sqrt(d2)) * repulse
          a.vy -= (dy / Math.sqrt(d2)) * repulse
          b.vx += (dx / Math.sqrt(d2)) * repulse
          b.vy += (dy / Math.sqrt(d2)) * repulse
        }
      }

      for (const edge of edges) {
        const a = index.get(edge.source)
        const b = index.get(edge.target)
        if (!a || !b) continue
        const dx = b.x - a.x
        const dy = b.y - a.y
        const dist = Math.max(1, Math.sqrt(dx * dx + dy * dy))
        const spring = (dist - TARGET_LINK) * 0.0018
        const fx = (dx / dist) * spring
        const fy = (dy / dist) * spring
        a.vx += fx
        a.vy += fy
        b.vx -= fx
        b.vy -= fy
      }

      const cx = width / 2
      const cy = height / 2
      for (const node of simNodes) {
        node.vx += (cx - node.x) * 0.0007
        node.vy += (cy - node.y) * 0.0007
        node.vx *= 0.86
        node.vy *= 0.86
        node.x = Math.max(20, Math.min(width - 20, node.x + node.vx))
        node.y = Math.max(20, Math.min(height - 20, node.y + node.vy))
      }
    }

    return new Map(simNodes.map((n) => [n.id, { x: n.x, y: n.y }]))
  }, [nodes, edges, size.height, size.width])

  const degree = useMemo(() => {
    const out = new Map<string, number>()
    for (const n of nodes) out.set(n.id, 0)
    for (const e of edges) {
      out.set(e.source, (out.get(e.source) ?? 0) + 1)
      out.set(e.target, (out.get(e.target) ?? 0) + 1)
    }
    return out
  }, [edges, nodes])

  const selectedNode = nodes.find((n) => n.id === selected) ?? null
  const selectedEdges = selected ? edges.filter((e) => e.source === selected || e.target === selected) : []

  return (
    <div className="space-y-4">
      <PageHeader title="Graph View" subtitle="Resource relations across references and dependencies." />
      {error ? <ErrorBanner message={error} /> : null}
      <div className="grid gap-4 lg:grid-cols-[1fr_320px]">
        <div className="rounded-2xl border border-gray-200 bg-white p-3">
          <div className="mb-2 text-sm text-gray-600">
            Nodes: <span className="font-semibold text-gray-900">{nodes.length}</span> / Edges:{' '}
            <span className="font-semibold text-gray-900">{edges.length}</span>
            {loading ? <span className="ml-2 text-gray-500">Loading...</span> : null}
          </div>
          <div ref={containerRef} className="h-[65vh] min-h-[420px] w-full overflow-hidden rounded-xl bg-slate-950/95">
            <svg width={size.width} height={size.height} viewBox={`0 0 ${size.width} ${size.height}`}>
              {edges.map((edge, i) => {
                const a = positions.get(edge.source)
                const b = positions.get(edge.target)
                if (!a || !b) return null
                const active = selected ? edge.source === selected || edge.target === selected : false
                return (
                  <line
                    key={`${edge.source}-${edge.target}-${i}`}
                    x1={a.x}
                    y1={a.y}
                    x2={b.x}
                    y2={b.y}
                    stroke={active ? '#f8fafc' : '#64748b'}
                    strokeOpacity={active ? 0.85 : 0.35}
                    strokeWidth={active ? 1.7 : 1}
                  />
                )
              })}
              {nodes.map((node) => {
                const p = positions.get(node.id)
                if (!p) return null
                const active = selected === node.id
                return (
                  <g key={node.id} onClick={() => setSelected(node.id)} className="cursor-pointer">
                    <circle
                      cx={p.x}
                      cy={p.y}
                      r={active ? NODE_RADIUS + 2 : NODE_RADIUS}
                      fill={active ? '#f59e0b' : '#38bdf8'}
                      fillOpacity={active ? 1 : 0.88}
                    />
                    <text x={p.x + 11} y={p.y - 10} fill="#e2e8f0" fontSize="11">
                      {node.resourceId}
                    </text>
                  </g>
                )
              })}
            </svg>
          </div>
        </div>

        <aside className="rounded-2xl border border-gray-200 bg-white p-4">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-500">Details</h2>
          {!selectedNode ? (
            <p className="mt-3 text-sm text-gray-600">Select a node to inspect its relations.</p>
          ) : (
            <div className="mt-3 space-y-3">
              <div>
                <p className="text-sm font-semibold text-gray-900">{selectedNode.name}</p>
                <p className="text-xs text-gray-600">{selectedNode.id}</p>
                <p className="mt-1 text-xs text-gray-600">Connected edges: {degree.get(selectedNode.id) ?? 0}</p>
                <Link to={`/resources/${selectedNode.type}/${selectedNode.resourceId}`} className="mt-2 inline-block text-sm text-blue-700 hover:text-blue-900">
                  Open Resource
                </Link>
              </div>
              <div>
                <h3 className="text-xs font-semibold uppercase tracking-wide text-gray-500">Relations</h3>
                <ul className="mt-2 max-h-[45vh] space-y-2 overflow-auto pr-1">
                  {selectedEdges.length === 0 ? <li className="text-sm text-gray-600">No relations</li> : null}
                  {selectedEdges.map((edge, idx) => {
                    const otherId = edge.source === selectedNode.id ? edge.target : edge.source
                    return (
                      <li key={`${otherId}-${idx}`} className="rounded-lg border border-gray-200 bg-gray-50 px-2 py-1.5 text-xs text-gray-700">
                        <span className="font-medium">{otherId}</span> ({edge.reason})
                      </li>
                    )
                  })}
                </ul>
              </div>
            </div>
          )}
        </aside>
      </div>
    </div>
  )
}
