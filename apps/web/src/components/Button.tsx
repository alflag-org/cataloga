import type { ButtonHTMLAttributes, ReactNode } from 'react'
import { Link } from 'react-router-dom'

type Variant = 'primary' | 'secondary' | 'danger'

type Props = ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: Variant
  children: ReactNode
}

const variantClasses: Record<Variant, string> = {
  primary: 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500',
  secondary: 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 focus:ring-gray-400',
  danger: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500'
}

const base = 'inline-flex items-center justify-center rounded-lg px-3 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50'

export function Button({ variant = 'primary', className = '', children, ...props }: Props) {
  return (
    <button className={`${base} ${variantClasses[variant]} ${className}`.trim()} {...props}>
      {children}
    </button>
  )
}

type LinkButtonProps = {
  to: string
  variant?: Variant
  children: ReactNode
  className?: string
}

export function LinkButton({ to, variant = 'primary', children, className = '' }: LinkButtonProps) {
  return (
    <Link to={to} className={`${base} ${variantClasses[variant]} ${className}`.trim()}>
      {children}
    </Link>
  )
}
