import { Link } from "react-router-dom";
import { deriveDisplayLabel } from "../types";
import type { GraphEdge, GraphNode } from "../graph/graphLayout";

type Props = {
  selectedNode: GraphNode | null;
  edges: GraphEdge[];
};

export function GraphDetailsPanel({ selectedNode, edges }: Props) {
  if (!selectedNode) {
    return (
      <aside className="rounded-2xl border border-gray-200 bg-white p-4">
        <h3 className="text-xs font-semibold uppercase tracking-wide text-gray-500">
          Selected resource
        </h3>
        <p className="mt-3 text-sm text-gray-600">
          Select a node to inspect relations.
        </p>
      </aside>
    );
  }

  const outgoing = edges.filter((edge) => edge.source === selectedNode.key);
  const incoming = edges.filter((edge) => edge.target === selectedNode.key);

  return (
    <aside className="rounded-2xl border border-gray-200 bg-white p-4">
      <h3 className="text-xs font-semibold uppercase tracking-wide text-gray-500">
        Selected resource
      </h3>
      <div className="mt-3 space-y-3 text-sm">
        <div>
          <p>
            <span className="font-medium">Type:</span> {selectedNode.typeTitle}
          </p>
          <p>
            <span className="font-medium">Name:</span> {selectedNode.name}
          </p>
          <p>
            <span className="font-medium">ID:</span> {selectedNode.resourceId}
          </p>
          <p>
            <span className="font-medium">Degree:</span> {selectedNode.degree}
          </p>
        </div>

        <div>
          <p className="font-medium text-gray-900">Outgoing references</p>
          {outgoing.length === 0 ? (
            <p className="text-xs text-gray-600">No outgoing references.</p>
          ) : (
            <ul className="mt-1 space-y-1 text-xs text-gray-700">
              {outgoing.map((edge, index) => (
                <li key={`${edge.source}-${edge.target}-${index}`}>
                  {deriveDisplayLabel(edge.field)}: {edge.target}
                </li>
              ))}
            </ul>
          )}
        </div>

        <div>
          <p className="font-medium text-gray-900">Incoming references</p>
          {incoming.length === 0 ? (
            <p className="text-xs text-gray-600">No incoming references.</p>
          ) : (
            <ul className="mt-1 space-y-1 text-xs text-gray-700">
              {incoming.map((edge, index) => (
                <li key={`${edge.source}-${edge.target}-${index}`}>
                  {deriveDisplayLabel(edge.field)}: {edge.source}
                </li>
              ))}
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
    </aside>
  );
}
