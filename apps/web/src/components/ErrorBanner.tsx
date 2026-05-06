export function ErrorBanner({ message }: { message: string | null }) {
  if (!message) return null;
  return (
    <div
      className="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
      role="alert"
    >
      <p className="font-semibold">Request failed</p>
      <p className="mt-1 break-words">{message}</p>
    </div>
  );
}
