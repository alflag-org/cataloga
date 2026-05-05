import type { ReactNode } from 'react'

type Props = {
  title: string
  actions?: ReactNode
  subtitle?: string
}

export function PageHeader({ title, actions, subtitle }: Props) {
  return (
    <div className="page-header">
      <div>
        <h1>{title}</h1>
        {subtitle ? <p>{subtitle}</p> : null}
      </div>
      <div>{actions}</div>
    </div>
  )
}
