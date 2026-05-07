import { useEffect, useRef, useState } from "react";
import { Breadcrumbs } from "./Breadcrumbs";
import { useI18n } from "../i18n";

export function Topbar() {
  const { lang, setLang, t } = useI18n();
  const [open, setOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    const onPointerDown = (event: PointerEvent) => {
      if (!rootRef.current) return;
      if (!rootRef.current.contains(event.target as Node)) {
        setOpen(false);
      }
    };
    window.addEventListener("pointerdown", onPointerDown);
    return () => window.removeEventListener("pointerdown", onPointerDown);
  }, []);

  return (
    <header className="sticky top-0 z-10 border-b border-gray-200 bg-white/95 backdrop-blur">
      <div className="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
        <Breadcrumbs />
        <div ref={rootRef} className="lang-switch">
          <button
            type="button"
            className={`lang-switch__button ${open ? "is-open" : ""}`}
            onClick={() => setOpen((prev) => !prev)}
            aria-expanded={open}
            aria-haspopup="menu"
            aria-label={t("Language")}
          >
            <span className="lang-switch__label">{t("Language")}</span>
            <span className="lang-switch__value">
              {lang === "ja" ? t("Japanese") : t("English")}
            </span>
            <svg
              className={`lang-switch__chevron ${open ? "is-open" : ""}`}
              viewBox="0 0 20 20"
              fill="none"
              aria-hidden="true"
            >
              <path
                d="M5 7.5L10 12.5L15 7.5"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </svg>
          </button>

          <div
            className={`lang-switch__menu ${open ? "is-open" : ""}`}
            role="menu"
            aria-label={t("Language")}
          >
            <button
              type="button"
              className={`lang-switch__item ${lang === "ja" ? "is-active" : ""}`}
              onClick={() => {
                setLang("ja");
                setOpen(false);
              }}
              role="menuitemradio"
              aria-checked={lang === "ja"}
            >
              <span className="lang-switch__item-name">{t("Japanese")}</span>
              <span className="lang-switch__item-code">JA</span>
            </button>
            <button
              type="button"
              className={`lang-switch__item ${lang === "en" ? "is-active" : ""}`}
              onClick={() => {
                setLang("en");
                setOpen(false);
              }}
              role="menuitemradio"
              aria-checked={lang === "en"}
            >
              <span className="lang-switch__item-name">{t("English")}</span>
              <span className="lang-switch__item-code">EN</span>
            </button>
          </div>
        </div>
      </div>
    </header>
  );
}
