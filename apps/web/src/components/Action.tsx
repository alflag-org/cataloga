import type { ButtonHTMLAttributes, ReactNode } from "react";
import { Link } from "react-router-dom";

type Tone = "primary" | "default" | "danger";

const toneClass: Record<Tone, string> = {
  primary: "text-blue-700 hover:text-blue-800",
  default: "text-gray-700 hover:text-gray-900",
  danger: "text-gray-700 hover:text-red-600",
};

const baseClass = "text-sm font-medium transition-colors";

type ActionLinkProps = {
  to: string;
  children: ReactNode;
  tone?: Tone;
  className?: string;
};

export function ActionLink({
  to,
  children,
  tone = "default",
  className = "",
}: ActionLinkProps) {
  return (
    <Link
      to={to}
      className={`${baseClass} ${toneClass[tone]} ${className}`.trim()}
    >
      {children}
    </Link>
  );
}

type ActionButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
  children: ReactNode;
  tone?: Tone;
};

export function ActionButton({
  children,
  tone = "default",
  className = "",
  ...props
}: ActionButtonProps) {
  return (
    <button
      className={`${baseClass} ${toneClass[tone]} ${className}`.trim()}
      {...props}
    >
      {children}
    </button>
  );
}
