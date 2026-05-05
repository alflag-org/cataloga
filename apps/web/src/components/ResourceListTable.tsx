import { Link } from 'react-router-dom'
import { readPath, type Resource } from '../types'

type Props = {
  type: string
  columns: string[]
  rows: Resource[]
}

export function ResourceListTable({ type, columns, rows }: Props) {
  return (
    <table>
      <thead>
        <tr>
          {columns.map((c) => (
            <th key={c}>{c}</th>
          ))}
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        {rows.map((r) => (
          <tr key={r.metadata.id}>
            {columns.map((c) => (
              <td key={c}>{readPath(r, c)}</td>
            ))}
            <td>
              <Link to={`/resource-types/${type}/${r.metadata.id}`}>View</Link>{' '}
              <Link to={`/resource-types/${type}/${r.metadata.id}/edit`}>Edit</Link>
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}
