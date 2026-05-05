type Props = {
  message?: string | null
}

export function ErrorBanner({ message }: Props) {
  if (!message) return null
  return <div className="error-banner">Error: {message}</div>
}
