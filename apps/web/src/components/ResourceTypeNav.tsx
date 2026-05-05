import { Link } from 'react-router-dom'
import type { ResourceType } from '../types'

type Props = {
  types: ResourceType[]
}

export function ResourceTypeNav({ types }: Props) {
  return (
    <ul>
      {types.map((rt) => (
        <li key={rt.id}>
          <Link to={`/resource-types/${rt.id}`}>{rt.title || rt.id}</Link>
        </li>
      ))}
    </ul>
  )
}
