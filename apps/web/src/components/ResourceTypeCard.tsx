import { Link } from "react-router-dom";

type Props = {
  title: string;
  typeId: string;
  count: number;
  to: string;
};

export function ResourceTypeCard({ title, typeId, count, to }: Props) {
  return (
    <Link
      to={to}
      className="block rounded-xl border border-gray-200 bg-white p-4 hover:border-gray-300 hover:bg-gray-50"
    >
      <p className="text-sm font-semibold text-gray-900">{title}</p>
      <p className="mt-1 text-sm text-gray-700">{count} resources</p>
      <p className="mt-1 text-xs text-gray-500">{typeId}</p>
    </Link>
  );
}
