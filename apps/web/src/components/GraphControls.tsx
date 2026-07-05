import { SelectInput } from "./SelectInput";
import { TextInput } from "./TextInput";
import { useI18n } from "../i18n";

type ShowMode = "all" | "connected" | "isolated";
export type GraphViewMode = "overview" | "focus" | "all";

type Props = {
  search: string;
  onSearchChange: (value: string) => void;
  viewMode: GraphViewMode;
  onViewModeChange: (value: GraphViewMode) => void;
  canFocus: boolean;
  groups: string[];
  selectedGroup: string;
  onGroupChange: (value: string) => void;
  showMode: ShowMode;
  onShowModeChange: (value: ShowMode) => void;
  showViewControls?: boolean;
};

export function GraphControls({
  search,
  onSearchChange,
  viewMode,
  onViewModeChange,
  canFocus,
  groups,
  selectedGroup,
  onGroupChange,
  showMode,
  onShowModeChange,
  showViewControls = true,
}: Props) {
  const { t } = useI18n();
  const viewModes: Array<{ value: GraphViewMode; label: string }> = [
    { value: "overview", label: t("Overview") },
    { value: "focus", label: t("Focus") },
    { value: "all", label: t("All") },
  ];
  const gridClassName = showViewControls
    ? "grid grid-cols-1 gap-3 lg:grid-cols-[1fr_240px_180px_200px]"
    : "grid grid-cols-1 gap-3 md:grid-cols-[minmax(220px,1fr)_180px_200px]";

  return (
    <div className={gridClassName}>
      <label className="text-sm text-gray-700">
        {t("Search")}
        <TextInput
          value={search}
          onChange={(event) => onSearchChange(event.target.value)}
          placeholder={t("Resource, ID, Resource Type")}
          className="mt-1 min-h-11"
        />
      </label>
      {showViewControls ? (
        <div className="text-sm text-gray-700">
          <span>{t("View")}</span>
          <div
            className="mt-1 grid grid-cols-3 overflow-hidden rounded-lg border border-gray-300 bg-white"
            role="group"
            aria-label={t("View")}
          >
            {viewModes.map((mode) => {
              const disabled = mode.value === "focus" && !canFocus;
              const active = viewMode === mode.value;
              return (
                <button
                  key={mode.value}
                  type="button"
                  disabled={disabled}
                  onClick={() => onViewModeChange(mode.value)}
                  aria-pressed={active}
                  className={[
                    "min-h-11 border-r border-gray-200 px-3 py-2 text-xs font-medium transition last:border-r-0 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500/40",
                    active
                      ? "bg-blue-600 text-white"
                      : "text-gray-700 hover:bg-gray-50",
                    disabled ? "cursor-not-allowed opacity-45" : "",
                  ].join(" ")}
                >
                  {mode.label}
                </button>
              );
            })}
          </div>
        </div>
      ) : null}
      <label className="text-sm text-gray-700">
        {t("Group")}
        <SelectInput
          value={selectedGroup}
          onChange={(event) => onGroupChange(event.target.value)}
          className="mt-1 min-h-11"
        >
          <option value="All">{t("All")}</option>
          {groups.map((group) => (
            <option key={group} value={group}>
              {group}
            </option>
          ))}
        </SelectInput>
      </label>
      <label className="text-sm text-gray-700">
        {t("Show")}
        <SelectInput
          value={showMode}
          onChange={(event) => onShowModeChange(event.target.value as ShowMode)}
          className="mt-1 min-h-11"
        >
          <option value="all">{t("All")}</option>
          <option value="connected">{t("Connected")}</option>
          <option value="isolated">{t("Isolated")}</option>
        </SelectInput>
      </label>
    </div>
  );
}
