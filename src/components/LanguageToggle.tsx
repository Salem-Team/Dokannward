"use client";

import { IconLanguage } from "@/components/Icons";
import { useLocale } from "@/context/locale";

type LanguageToggleProps = {
  className?: string;
  /**
   * `text` — editorial label of the *other* locale (desktop header).
   * `icon` — globe icon (mobile header).
   * `segmented` — EN | ع (menus / drawers).
   */
  variant?: "text" | "icon" | "segmented" | "chip";
};

/**
 * Bilingual control.
 * Text shows the language you can switch to (boutique pattern).
 * Icon is a compact globe for dense headers.
 * Segmented shows both options for menus.
 */
export function LanguageToggle({
  className = "",
  variant = "text",
}: LanguageToggleProps) {
  const { locale, setLocale, t } = useLocale();
  const next = locale === "ar" ? "en" : "ar";
  const label = next === "ar" ? t("lang.switchToAr") : t("lang.switchToEn");

  if (variant === "icon") {
    return (
      <button
        type="button"
        className={`site-header__icon lang-icon ${className}`.trim()}
        aria-label={t("a11y.lang")}
        title={label}
        onClick={() => setLocale(next)}
      >
        <IconLanguage size={18} />
      </button>
    );
  }

  if (variant === "text" || variant === "chip") {
    return (
      <button
        type="button"
        className={`lang-text${next === "ar" ? " lang-text--ar" : " lang-text--en"} ${className}`.trim()}
        aria-label={t("a11y.lang")}
        onClick={() => setLocale(next)}
      >
        <span className="lang-text__label">{label}</span>
      </button>
    );
  }

  return (
    <div
      className={`lang-switch ${className}`.trim()}
      role="group"
      aria-label={t("a11y.lang")}
    >
      <button
        type="button"
        className={`lang-switch__opt${locale === "en" ? " is-active" : ""}`}
        aria-pressed={locale === "en"}
        aria-label={t("lang.short.en")}
        onClick={() => setLocale("en")}
      >
        EN
      </button>
      <span className="lang-switch__rule" aria-hidden="true" />
      <button
        type="button"
        className={`lang-switch__opt lang-switch__opt--ar${locale === "ar" ? " is-active" : ""}`}
        aria-pressed={locale === "ar"}
        aria-label={t("lang.short.ar")}
        onClick={() => setLocale("ar")}
      >
        ع
      </button>
    </div>
  );
}
