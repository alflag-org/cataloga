import { SelectInput } from "./SelectInput";
import { TextInput } from "./TextInput";
import { useI18n } from "../i18n";

type ShowMode = "all" | "connected" | "isolated";

type Props = {
  search: string;
  onSearchChange: (value: string) => void;
  groups: string[];
  selectedGroup: string;
  onGroupChange: (value: string) => void;
  showMode: ShowMode;
  onShowModeChange: (value: ShowMode) => void;
};

export function GraphControls({
  search,
  onSearchChange,
  groups,
  selectedGroup,
  onGroupChange,
  showMode,
  onShowModeChange,
}: Props) {
  const { t } = useI18n();
  return (
    <div className="grid grid-cols-1 gap-3 lg:grid-cols-[1fr_180px_200px]">
      <label className="text-sm text-gray-700">
        {t("Search")}
        <TextInput
          value={search}
          onChange={(event) => onSearchChange(event.target.value)}
          placeholder={t("Resource, ID, Resource Type")}
        />
      </label>
      <label className="text-sm text-gray-700">
        {t("Group")}
        <SelectInput
          value={selectedGroup}
          onChange={(event) => onGroupChange(event.target.value)}
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
        {t("ShowMode")}
        <SelectInput
          value={showMode}
          onChange={(event) => onShowModeChange(event.target.value as ShowMode)}
        >
          <option value="all">{t("All")}</option>
          <option value="connected">{t("Connected")}</option>
          <option value="isolated">{t("Isolated")}</option>
        </SelectInput>
      </label>
    </div>
  );
}
