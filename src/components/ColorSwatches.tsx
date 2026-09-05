"use client";

import type { CSSProperties } from "react";
import type { ProductColor } from "@/lib/catalog";
import { useLocale } from "@/context/locale";

type ColorSwatchesProps = {
  colors: ProductColor[];
  selected?: ProductColor | null;
  onSelect?: (color: ProductColor) => void;
  size?: "sm" | "md";
};

/**
 * Color picker — solid hex swatches only.
 * Color photos still swap the product hero when a swatch is selected.
 */
export function ColorSwatches({
  colors,
  selected,
  onSelect,
  size = "md",
}: ColorSwatchesProps) {
  const { t } = useLocale();
  if (!colors || colors.length === 0) return null;

  return (
    <div
      className="color-swatches"
      role="radiogroup"
      aria-label={t("a11y.colors")}
    >
      {colors.map((color) => {
        // Colors with multiple sizes share one swatch identity; compare by
        // name so picking a size never deselects the color chip.
        const isSelected = selected?.name === color.name;
        return (
          <button
            key={`${color.name}-${color.variantId}`}
            type="button"
            role="radio"
            aria-checked={isSelected}
            aria-label={
              color.inStock ? color.name : `${color.name} (out of stock)`
            }
            title={color.name}
            disabled={!onSelect}
            onClick={(e) => {
              e.preventDefault();
              e.stopPropagation();
              onSelect?.(color);
            }}
            className={[
              "color-swatch",
              size === "sm" ? "color-swatch--sm" : "color-swatch--md",
              isSelected ? "is-selected" : "",
              !color.inStock ? "is-oos" : "",
              onSelect ? "is-interactive" : "",
            ]
              .filter(Boolean)
              .join(" ")}
            style={
              {
                backgroundColor: color.hex,
                "--swatch": color.hex,
              } as CSSProperties
            }
          >
            {!color.inStock && <span className="color-swatch__slash" />}
          </button>
        );
      })}
    </div>
  );
}
