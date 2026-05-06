import type { SelectHTMLAttributes } from "react";

export function SelectInput({
  className = "",
  children,
  ...props
}: SelectHTMLAttributes<HTMLSelectElement>) {
  return (
    <select
      {...props}
      className={`block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-800 focus:border-blue-500 focus:ring-blue-500 ${className}`.trim()}
    >
      {children}
    </select>
  );
}
