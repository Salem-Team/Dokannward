"use client";

import { BRAND } from "@/lib/brand";
import { useLocale } from "@/context/locale";

/** Presentational brand loader — cream seal + soft bronze pulse. */
export function BrandLoader({
  compact = false,
  label,
  logoSrc,
  brandName,
}: {
  compact?: boolean;
  label?: string;
  logoSrc?: string;
  brandName?: string;
}) {
  const { t } = useLocale();
  const src = logoSrc?.trim() || BRAND.logo;
  const name = brandName?.trim() || BRAND.name;
  const resolvedLabel = label ?? t("loading.default");

  return (
    <div
      className={`brand-loader${compact ? " brand-loader--compact" : ""}`}
      role="status"
      aria-live="polite"
      aria-busy="true"
    >
      <div className="brand-loader__stage" aria-hidden="true">
        <span className="brand-loader__glow" />
        <span className="brand-loader__ring" />
        <span className="brand-loader__ring brand-loader__ring--delayed" />
        <div className="brand-loader__logo-wrap">
          { }
          <img
            src={src}
            alt={name}
            width={160}
            height={160}
            className="brand-loader__logo"
            decoding="async"
          />
          <span className="brand-loader__shine" />
        </div>
      </div>

      <div className="brand-loader__stripes brand-loader__bloom" aria-hidden="true">
        <span />
        <span />
        <span />
        <span />
        <span />
      </div>

      <span className="sr-only">{resolvedLabel}</span>
    </div>
  );
}
