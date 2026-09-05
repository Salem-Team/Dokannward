"use client";

import { StorefrontImage } from "@/components/StorefrontImage";
import Link from "next/link";
import { useState } from "react";
import type { Product, ProductColor } from "@/lib/catalog";
import type { ProductCardData } from "@/lib/product-card";
import { IMAGE_QUALITY } from "@/lib/media";
import { useCart } from "@/context/cart";
import { useCurrency } from "@/context/currency";
import { useLocale } from "@/context/locale";
import { IconBag, IconCheck, IconEye } from "@/components/Icons";
import { ColorSwatches } from "@/components/ColorSwatches";
import { WishlistHeart } from "@/components/WishlistHeart";
import { isOnSale, ProductPrice } from "@/components/ProductPrice";

export type { ProductCardData } from "@/lib/product-card";
export { toProductCardData } from "@/lib/product-card";

export function ProductCardClient({
  product,
  priority = false,
  priceLabel,
  compareAtLabel,
}: {
  product: ProductCardData;
  priority?: boolean;
  /** Server-formatted fallback so first paint doesn't wait on currency context. */
  priceLabel?: string;
  compareAtLabel?: string;
}) {
  const { addItem } = useCart();
  const { format } = useCurrency();
  const { t } = useLocale();
  const [added, setAdded] = useState(false);
  const [useSecondaryImage, setUseSecondaryImage] = useState(false);
  const [imgDead, setImgDead] = useState(false);
  const [selectedColor, setSelectedColor] = useState<ProductColor | null>(
    () => product.colors[0] ?? null,
  );
  /** Keep the admin "Main" cover until the shopper picks a swatch. */
  const [colorDrivesImage, setColorDrivesImage] = useState(false);
  const primaryImage =
    colorDrivesImage && selectedColor?.image
      ? selectedColor.image
      : product.image || selectedColor?.image || "";
  const secondaryImage =
    primaryImage && product.image && primaryImage !== product.image
      ? product.image
      : primaryImage && selectedColor?.image && primaryImage !== selectedColor.image
        ? selectedColor.image
        : "";
  const displayImage =
    useSecondaryImage && secondaryImage ? secondaryImage : primaryImage;
  const displayPrice = selectedColor?.price ?? product.price;
  const displayCompareAt =
    selectedColor?.compareAtPrice ?? product.compareAtPrice;
  const canAdd = selectedColor ? selectedColor.inStock : product.available;
  const showImage = Boolean(displayImage) && !imgDead;
  const onSale = isOnSale(displayPrice, displayCompareAt);

  const pickColor = (color: ProductColor) => {
    setSelectedColor(color);
    setColorDrivesImage(true);
    setUseSecondaryImage(false);
    setImgDead(false);
  };

  const handleImageError = () => {
    if (!useSecondaryImage && secondaryImage) {
      setUseSecondaryImage(true);
      return;
    }
    setImgDead(true);
  };

  const handleAdd = () => {
    if (!canAdd || added) return;
    addItem(product as Product, 1, selectedColor ?? undefined);
    setAdded(true);
    window.setTimeout(() => setAdded(false), 1400);
  };

  return (
    <article className="product-card">
      <div className="product-card__media">
        <div className="product-card__media-clip">
          <Link
            href={`/products/${product.handle}`}
            prefetch={priority}
            className="product-card__media-link"
            aria-label={product.title}
          >
            {showImage ? (
              <StorefrontImage
                key={`${selectedColor?.variantId ?? "default"}-${displayImage}`}
                src={displayImage}
                alt={product.title}
                fill
                sizes="(max-width: 768px) 50vw, (max-width: 1200px) 33vw, 25vw"
                quality={IMAGE_QUALITY.card}
                loading={priority ? "eager" : "lazy"}
                priority={priority}
                className="product-card__image media-mono"
                onError={handleImageError}
              />
            ) : (
              <span className="product-card__image-fallback" aria-hidden="true" />
            )}
          </Link>

          <div className="product-card__veil" aria-hidden="true" />

          {!canAdd ? (
            <span className="product-card__badge">{t("product.soldOut")}</span>
          ) : onSale ? (
            <span className="product-card__badge product-card__badge--sale">
              {t("product.sale")}
            </span>
          ) : null}

          <WishlistHeart product={product as Product} className="product-card__wish" />

          <div className="product-card__actions">
            <button
              type="button"
              className={`product-card__btn product-card__btn--primary${added ? " is-added" : ""}`}
              disabled={!canAdd}
              onClick={handleAdd}
              aria-label={
                added
                  ? t("product.addedToCart")
                  : t("product.addNamed", { title: product.title })
              }
            >
              <span className="product-card__btn-label">
                {added ? (
                  <>
                    <IconCheck size={13} />
                    <span className="product-card__btn-text">{t("product.added")}</span>
                  </>
                ) : (
                  <>
                    <IconBag size={13} />
                    <span className="product-card__btn-text">{t("product.add")}</span>
                  </>
                )}
              </span>
            </button>
            <Link
              href={`/products/${product.handle}`}
              className="product-card__btn product-card__btn--ghost"
            >
              <span className="product-card__btn-label">
                <IconEye size={13} />
                <span className="product-card__btn-text">{t("product.choose")}</span>
              </span>
            </Link>
          </div>
        </div>
      </div>

      <div className="product-card__meta">
        <h3 className="product-card__title heading" dir="auto">
          <Link href={`/products/${product.handle}`}>{product.title}</Link>
        </h3>
        <ProductPrice
          price={displayPrice}
          compareAtPrice={displayCompareAt}
          format={format}
          priceLabel={selectedColor ? undefined : priceLabel}
          compareAtLabel={selectedColor ? undefined : compareAtLabel}
          size="card"
          className="product-card__price"
        />

        {product.colors.length > 0 && (
          <div className="product-card__colors">
            <ColorSwatches
              colors={product.colors}
              selected={selectedColor}
              size="sm"
              onSelect={pickColor}
            />
          </div>
        )}
      </div>
    </article>
  );
}
