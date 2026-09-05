"use client";

import { useLocale } from "@/context/locale";

export type SizeSwatchOption = {
  name: string;
  inStock: boolean;
};

type SizeSwatchesProps = {
  sizes: SizeSwatchOption[];
  selected?: string | null;
  onSelect?: (size: string) => void;
};

/**
 * Size picker for shoe numbers and clothing labels (S / M / L, custom text).
 * Offered sizes always render; unavailable ones keep a diagonal slash so
 * shoppers see the full run without being able to buy crossed-out options.
 */
export function SizeSwatches({
  sizes,
  selected,
  onSelect,
}: SizeSwatchesProps) {
  const { t } = useLocale();
  if (!sizes.length) return null;

  return (
    <div className="size-swatches" role="radiogroup" aria-label={t("a11y.sizes")}>
      {sizes.map((size) => {
        const isSelected = selected === size.name;
        const isTextSize = /[A-Za-z]/.test(size.name);
        return (
          <button
            key={size.name}
            type="button"
            role="radio"
            aria-checked={isSelected}
            aria-label={
              size.inStock ? `Size ${size.name}` : `Size ${size.name} unavailable`
            }
            title={
              size.inStock ? `Size ${size.name}` : `Size ${size.name} — unavailable`
            }
            disabled={!onSelect || !size.inStock}
            onClick={(e) => {
              e.preventDefault();
              e.stopPropagation();
              if (!size.inStock) return;
              onSelect?.(size.name);
            }}
            className={[
              "size-swatch",
              isTextSize ? "is-text" : "",
              isSelected ? "is-selected" : "",
              !size.inStock ? "is-oos" : "",
              onSelect && size.inStock ? "is-interactive" : "",
            ]
              .filter(Boolean)
              .join(" ")}
          >
            <span className="size-swatch__label">{size.name}</span>
            {!size.inStock ? (
              <span className="size-swatch__slash" aria-hidden="true" />
            ) : null}
          </button>
        );
      })}
    </div>
  );
}
