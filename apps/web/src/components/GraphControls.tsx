import { SelectInput } from "./SelectInput";
import { TextInput } from "./TextInput";

type ShowMode = "all" | "connected" | "isolated";

type Props = {
  search: string;
  onSearchChange: (value: string) => void;
  groups: string[];
  selectedGroup: string;
  onGroupChange: (value: string) => void;
  showMode: ShowMode;
  onShowModeChange: (value: ShowMode) => void;
  zoomPercent: number;
  onZoomIn: () => void;
  onZoomOut: () => void;
  onResetZoom: () => void;
  onFit: () => void;
};

export function GraphControls({
  search,
  onSearchChange,
  groups,
  selectedGroup,
  onGroupChange,
  showMode,
  onShowModeChange,
  zoomPercent,
  onZoomIn,
  onZoomOut,
  onResetZoom,
  onFit,
}: Props) {
  return (
    <div className="grid grid-cols-1 gap-3 lg:grid-cols-[1fr_180px_200px_auto]">
      <label className="text-sm text-gray-700">
        Search
        <TextInput
          value={search}
          onChange={(event) => onSearchChange(event.target.value)}
          placeholder="Resource, ID, Resource Type"
        />
      </label>
      <label className="text-sm text-gray-700">
        Group
        <SelectInput
          value={selectedGroup}
          onChange={(event) => onGroupChange(event.target.value)}
        >
          <option value="All">All</option>
          {groups.map((group) => (
            <option key={group} value={group}>
              {group}
            </option>
          ))}
        </SelectInput>
      </label>
      <label className="text-sm text-gray-700">
        Show
        <SelectInput
          value={showMode}
          onChange={(event) => onShowModeChange(event.target.value as ShowMode)}
        >
          <option value="all">All</option>
          <option value="connected">Connected</option>
          <option value="isolated">Isolated</option>
        </SelectInput>
      </label>
      <div className="flex items-end gap-2">
        <button
          type="button"
          onClick={onZoomOut}
          className="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
          aria-label="Zoom out"
        >
          −
        </button>
        <button
          type="button"
          onClick={onResetZoom}
          className="min-w-[62px] rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
          aria-label="Reset zoom"
        >
          {zoomPercent}%
        </button>
        <button
          type="button"
          onClick={onZoomIn}
          className="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
          aria-label="Zoom in"
        >
          +
        </button>
        <button
          type="button"
          onClick={onFit}
          className="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
        >
          Fit
        </button>
      </div>
    </div>
  );
}
