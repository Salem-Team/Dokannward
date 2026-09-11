"use client";

import { useState } from "react";
import { Reveal } from "@/components/Reveal";
import {
  ProductCardClient,
  type ProductCardData,
} from "@/components/ProductCardClient";
import { useLocale } from "@/context/locale";

export type HomeArrivalsItem = {
  product: ProductCardData;
  priceLabel: string;
  compareAtLabel?: string;
};

/** 2 cols × 3 rows (mobile) / 4 cols × 3 rows (desktop). */
const MOBILE_CAP = 6;
const DESKTOP_CAP = 12;

/**
 * New arrivals grid: 3 rows by default.
 * Extra items unlock behind Show more (responsive caps via CSS).
 */
export function HomeArrivalsGrid({ items }: { items: HomeArrivalsItem[] }) {
  const { t } = useLocale();
  const [expanded, setExpanded] = useState(false);

  if (items.length === 0) return null;

  const needsMobileMore = items.length > MOBILE_CAP;
  const needsDesktopMore = items.length > DESKTOP_CAP;
  const canToggle = needsMobileMore || needsDesktopMore;

  return (
    <div className="home-arrivals">
      <div
        className={[
          "grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-5 home-arrivals-grid",
          expanded ? "is-expanded" : "is-collapsed",
        ].join(" ")}
      >
        {items.map((item, i) => (
          <Reveal
            key={item.product.id || item.product.handle}
            delay={Math.min(i * 55, 360)}
            className="home-arrivals__card"
          >
            <ProductCardClient
              product={item.product}
              priority={i < 4}
              priceLabel={item.priceLabel}
              compareAtLabel={item.compareAtLabel}
            />
          </Reveal>
        ))}
      </div>

      {canToggle ? (
        <div
          className={[
            "home-arrivals__more",
            !needsDesktopMore ? "home-arrivals__more--mobile-only" : "",
          ]
            .filter(Boolean)
            .join(" ")}
        >
          <button
            type="button"
            className="home-arrivals__more-btn"
            aria-expanded={expanded}
            onClick={() => setExpanded((v) => !v)}
          >
            {expanded
              ? t("home.arrivals.showLess")
              : t("home.arrivals.showMore")}
          </button>
        </div>
      ) : null}
    </div>
  );
}
