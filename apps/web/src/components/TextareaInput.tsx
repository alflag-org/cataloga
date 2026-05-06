import type { TextareaHTMLAttributes } from 'react'

export function TextareaInput({ className = '', ...props }: TextareaHTMLAttributes<HTMLTextAreaElement>) {
  return (
    <textarea
      {...props}
      className={`block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-800 placeholder:text-gray-400 focus:border-blue-500 focus:ring-blue-500 ${className}`.trim()}
    />
  )
}
