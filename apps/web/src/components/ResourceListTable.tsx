import { Link } from 'react-router-dom'
import { readPath, type Resource } from '../types'
import { Badge } from './Badge'
import { Button } from './Button'

type Props = {
  type: string
  columns: string[]
  rows: Resource[]
  sortBy: string
  sortDir: 'asc' | 'desc'
  onSort: (column: string) => void
  onDelete: (resourceId: string) => void
}

function toCompact(value: unknown): string {
  if (Array.isArray(value)) return value.join(', ')
  if (value && typeof value === 'object') return JSON.stringify(value)
  if (value == null) return ''
  return String(value)
}

export function ResourceListTable({ type, columns, rows, sortBy, sortDir, onSort, onDelete }: Props) {
  return (
    <div className="overflow-x-auto">
      <table className="min-w-full divide-y divide-gray-200 text-sm">
        <thead className="bg-gray-50">
          <tr>
            {columns.map((c) => (
              <th key={c} className="px-3 py-2 text-left font-semibold text-gray-600">
                <button className="inline-flex items-center gap-1 hover:text-gray-900" onClick={() => onSort(c)}>
                  {c}
                  {sortBy === c ? (sortDir === 'asc' ? '↑' : '↓') : ''}
                </button>
              </th>
            ))}
            <th className="px-3 py-2 text-left font-semibold text-gray-600">Actions</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-100 bg-white">
          {rows.map((r) => (
            <tr key={r.metadata.id} className="hover:bg-gray-50">
              {columns.map((c) => (
                <td key={c} className="max-w-xs px-3 py-2 align-top text-gray-700">
                  <span className="line-clamp-2 break-all">{toCompact(readPath(r, c))}</span>
                </td>
              ))}
              <td className="px-3 py-2">
                <div className="flex items-center gap-2">
                  <Link className="text-blue-600 hover:text-blue-700" to={`/resource-types/${type}/${r.metadata.id}`}>
                    View
                  </Link>
                  <Badge>•</Badge>
                  <Link className="text-gray-700 hover:text-gray-900" to={`/resource-types/${type}/${r.metadata.id}/edit`}>
                    Edit
                  </Link>
                  <Badge>•</Badge>
                  <Button variant="danger" className="px-2 py-1 text-xs" onClick={() => onDelete(r.metadata.id)}>
                    Delete
                  </Button>
                </div>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
