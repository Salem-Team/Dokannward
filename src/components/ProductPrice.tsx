"use client";

type ProductPriceProps = {
  price: string | number;
  compareAtPrice?: string | number | null;
  format: (value: string | number) => string;
  /** Server-formatted current price for first paint. */
  priceLabel?: string;
  /** Server-formatted compare-at price for first paint. */
  compareAtLabel?: string;
  size?: "card" | "detail";
  className?: string;
};

function parseAmount(value: string | number | null | undefined): number | null {
  if (value == null || value === "") return null;
  const n = typeof value === "number" ? value : parseFloat(value);
  return Number.isFinite(n) ? n : null;
}

/** Current price with optional struck-through original + save badge. */
export function ProductPrice({
  price,
  compareAtPrice = null,
  format,
  priceLabel,
  compareAtLabel,
  size = "card",
  className = "",
}: ProductPriceProps) {
  const current = parseAmount(price);
  const compare = parseAmount(compareAtPrice);
  const onSale =
    current != null && compare != null && compare > current && current >= 0;
  const savePct = onSale
    ? Math.round(((compare - current) / compare) * 100)
    : 0;

  return (
    <p
      className={["product-price", `product-price--${size}`, className]
        .filter(Boolean)
        .join(" ")}
    >
      <span className="product-price__current">
        {priceLabel && !onSale ? priceLabel : format(price)}
      </span>
      {onSale ? (
        <>
          <span className="product-price__was">
            {compareAtLabel || format(compareAtPrice!)}
          </span>
          {savePct > 0 ? (
            <span className="product-price__save">−{savePct}%</span>
          ) : null}
        </>
      ) : null}
    </p>
  );
}

export function isOnSale(
  price: string | number,
  compareAtPrice?: string | number | null,
): boolean {
  const current = parseAmount(price);
  const compare = parseAmount(compareAtPrice);
  return current != null && compare != null && compare > current;
}
