import { Breadcrumbs } from "./Breadcrumbs";
import { useI18n } from "../i18n";

export function Topbar() {
  const { lang, setLang, t } = useI18n();
  return (
    <header className="sticky top-0 z-10 border-b border-gray-200 bg-white/95 backdrop-blur">
      <div className="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
        <Breadcrumbs />
        <label className="flex items-center gap-2 text-xs text-gray-600">
          {t("Language")}
          <select
            className="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs"
            value={lang}
            onChange={(e) => setLang(e.target.value as "ja" | "en")}
          >
            <option value="ja">{t("Japanese")}</option>
            <option value="en">{t("English")}</option>
          </select>
        </label>
      </div>
    </header>
  );
}
