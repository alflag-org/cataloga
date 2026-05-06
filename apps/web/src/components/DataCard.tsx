import type { ReactNode } from "react";

export function DataCard({
  title,
  actions,
  children,
}: {
  title?: string;
  actions?: ReactNode;
  children: ReactNode;
}) {
  return (
    <section className="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
      {title || actions ? (
        <div className="mb-4 flex items-center justify-between gap-3">
          {title ? (
            <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-600">
              {title}
            </h2>
          ) : (
            <div />
          )}
          {actions}
        </div>
      ) : null}
      {children}
    </section>
  );
}
