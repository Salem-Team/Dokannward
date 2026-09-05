"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import type { ProductColor, ProductColorSize } from "@/lib/catalog";
import {
  toCartProduct,
  type ProductDetailClientData,
} from "@/lib/product-detail";
import { useBrand } from "@/context/brand";
import { useCart } from "@/context/cart";
import { useCurrency } from "@/context/currency";
import { useInventorySettings } from "@/context/inventory-settings";
import { useLocale } from "@/context/locale";
import { ColorSwatches } from "@/components/ColorSwatches";
import { SizeSwatches } from "@/components/SizeSwatches";
import { IconCheck, IconMinus, IconPlus, IconStar } from "@/components/Icons";
import { ProductGallery } from "@/components/ProductGallery";
import { ProductPrice } from "@/components/ProductPrice";
import { WishlistHeart } from "@/components/WishlistHeart";

function firstAvailableSize(
  sizes: ProductColorSize[],
  preferred?: string | null,
): ProductColorSize | null {
  if (!sizes.length) return null;
  if (preferred) {
    const match = sizes.find((s) => s.name === preferred && s.inStock);
    if (match) return match;
  }
  return sizes.find((s) => s.inStock) ?? sizes[0] ?? null;
}

export function ProductDetailClient({
  product,
  shortDescription,
  description,
}: {
  product: ProductDetailClientData;
  shortDescription: string | null;
  description: string | null;
}) {
  const { addItem } = useCart();
  const { format } = useCurrency();
  const inventory = useInventorySettings();
  const brand = useBrand();
  const { t } = useLocale();
  const [qty, setQty] = useState(1);
  const [added, setAdded] = useState(false);
  const [selectedColor, setSelectedColor] = useState<ProductColor | null>(
    () => product.colors[0] ?? null,
  );
  const [selectedSizeName, setSelectedSizeName] = useState<string | null>(null);
  /** Keep PDP hero on the product primary until the shopper picks a swatch. */
  const [colorDrivesImage, setColorDrivesImage] = useState(false);

  const cartProduct = toCartProduct(product);
  const productImages = useMemo(() => {
    if (product.images?.length) return product.images;
    return product.image ? [product.image] : [];
  }, [product.image, product.images]);

  /**
   * A color with its own photo set owns the gallery from first paint, so the
   * pre-selected swatch never shows other colors. Colors without uploads keep
   * the shared product gallery instead of an empty stage.
   */
  const colorImages = selectedColor?.images ?? [];
  const showingColorSet = colorImages.length > 0;
  const galleryImages = showingColorSet ? colorImages : productImages;
  const showColorLabel = Boolean(
    selectedColor && (showingColorSet || colorDrivesImage),
  );

  const colorSizes = selectedColor?.sizes ?? [];
  const hasSizes =
    (product.sizes?.length ?? 0) > 0 && colorSizes.length > 0;
  const offeredSizes = useMemo(() => {
    if (!hasSizes) return [];
    // Always show the product's full size run so unavailable sizes keep a
    // crossed-out chip even when this color is missing a size row.
    const names =
      product.sizes && product.sizes.length
        ? product.sizes
        : colorSizes.map((s) => s.name);
    return names.map((name) => {
      const option = colorSizes.find((s) => s.name === name);
      return {
        name,
        inStock: Boolean(option?.inStock),
        option: option ?? null,
      };
    });
  }, [hasSizes, product.sizes, colorSizes]);

  const activeSize = hasSizes
    ? firstAvailableSize(colorSizes, selectedSizeName)
    : null;

  useEffect(() => {
    if (!hasSizes) {
      setSelectedSizeName(null);
      return;
    }
    const next = firstAvailableSize(colorSizes, selectedSizeName);
    setSelectedSizeName(next?.name ?? null);
    // Only re-sync when the color (and therefore its size matrix) changes.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedColor?.name, hasSizes]);

  // With a full per-color set the gallery already shows the right slides, so
  // overriding would strip the shopper's ability to browse the other angles.
  const overrideImage =
    colorDrivesImage && !showingColorSet && selectedColor?.image
      ? selectedColor.image
      : null;
  const activePrice =
    activeSize?.price ?? selectedColor?.price ?? product.price;
  const activeCompareAt =
    selectedColor?.compareAtPrice ?? product.compareAtPrice;
  const activeStock = hasSizes
    ? (activeSize?.stock ?? null)
    : (selectedColor?.stock ?? product.stock ?? null);
  const canAdd = hasSizes
    ? Boolean(activeSize?.inStock)
    : selectedColor
      ? selectedColor.inStock
      : product.available;

  const handleAdd = () => {
    if (!canAdd) return;

    let cartColor = selectedColor ?? undefined;
    if (selectedColor && activeSize) {
      cartColor = {
        ...selectedColor,
        variantId: activeSize.variantId,
        sku: activeSize.sku,
        price: activeSize.price,
        inStock: activeSize.inStock,
        stock: activeSize.stock,
        size: activeSize.name,
      };
    }

    addItem(cartProduct, qty, cartColor);
    setAdded(true);
    window.setTimeout(() => setAdded(false), 1600);
  };

  const pickColor = (color: ProductColor) => {
    setSelectedColor(color);
    setColorDrivesImage(true);
    if (hasSizes || (color.sizes?.length ?? 0) > 0) {
      const next = firstAvailableSize(color.sizes ?? [], selectedSizeName);
      setSelectedSizeName(next?.name ?? null);
      const stock = next?.stock;
      if (stock != null) {
        setQty((current) => Math.min(current, Math.max(1, stock)));
      }
      return;
    }
    const stock = color.stock;
    if (stock != null) {
      setQty((current) => Math.min(current, Math.max(1, stock)));
    }
  };

  const pickSize = (name: string) => {
    setSelectedSizeName(name);
    const option = colorSizes.find((s) => s.name === name);
    if (option?.stock != null) {
      setQty((current) => Math.min(current, Math.max(1, option.stock!)));
    }
  };

  return (
    <div className="grid md:grid-cols-2 gap-8 md:gap-14">
      <ProductGallery
        images={galleryImages}
        alt={
          showColorLabel && selectedColor
            ? `${product.title} — ${selectedColor.name}`
            : product.title
        }
        overrideImage={overrideImage}
        onSelectGalleryImage={() => setColorDrivesImage(false)}
        chip={
          showColorLabel && selectedColor ? (
            <span className="product-hero-media__chip">
              <span
                className="product-hero-media__chip-dot"
                style={{ backgroundColor: selectedColor.hex }}
              />
              {selectedColor.name}
              {activeSize ? ` · ${activeSize.name}` : ""}
            </span>
          ) : null
        }
      />

      <div className="md:py-6">
        <p className="product-detail__brand mb-3">
          {product.vendor}
        </p>
        <h1 className="heading product-detail__title mb-2">{product.title}</h1>

        {product.reviewsCount > 0 && (
          <a
            href="#reviews"
            className="flex items-center gap-1.5 text-sm opacity-70 mb-4 hover:opacity-100 transition-opacity"
          >
            <span className="flex items-center gap-0.5 text-[var(--color-accent,#b08d57)]">
              {Array.from({ length: 5 }).map((_, i) => (
                <IconStar
                  key={i}
                  size={13}
                  className={
                    i < Math.round(product.ratingAverage ?? 0)
                      ? "opacity-100"
                      : "opacity-25"
                  }
                  fill={
                    i < Math.round(product.ratingAverage ?? 0)
                      ? "currentColor"
                      : "none"
                  }
                />
              ))}
            </span>
            {product.ratingAverage?.toFixed(1)} · {product.reviewsCount} review
            {product.reviewsCount === 1 ? "" : "s"}
          </a>
        )}

        <ProductPrice
          price={activePrice}
          compareAtPrice={activeCompareAt}
          format={format}
          size="detail"
          className={
            canAdd && inventory.show_stock_status
              ? "product-price--with-stock"
              : "product-price--spaced"
          }
        />

        {!canAdd && (
          <p className="inline-block mb-6 text-sm uppercase tracking-wide bg-black/5 px-3 py-1.5">
            {t("product.soldOut")}
          </p>
        )}

        {canAdd && inventory.show_stock_status && (
          <p className="product-stock" aria-live="polite">
            <span className="product-stock__dot" aria-hidden="true" />
            {inventory.show_stock_quantity && activeStock != null
              ? activeStock === 1
                ? t("product.unitAvailable")
                : t("product.unitsAvailable", { count: activeStock })
              : t("product.inStock")}
          </p>
        )}

        {product.colors.length > 0 && (
          <div className="mb-7">
            <p className="text-xs uppercase tracking-[0.12em] opacity-50 mb-3">
              {t("product.color")}
              {selectedColor ? ` — ${selectedColor.name}` : ""}
            </p>
            <ColorSwatches
              colors={product.colors}
              selected={selectedColor}
              onSelect={pickColor}
            />
          </div>
        )}

        {hasSizes && (
          <div className="mb-7">
            <p className="text-xs uppercase tracking-[0.12em] opacity-50 mb-3">
              Size{selectedSizeName ? ` — ${selectedSizeName}` : ""}
            </p>
            <SizeSwatches
              sizes={offeredSizes}
              selected={selectedSizeName}
              onSelect={pickSize}
            />
            <p className="mt-2.5 text-xs opacity-45" aria-live="polite">
              {t("product.sizesAvailable")}{" "}
              {offeredSizes.map((s, index) => (
                <span key={s.name}>
                  {index > 0 ? " / " : null}
                  <span className={s.inStock ? undefined : "size-run__oos"}>
                    {s.name}
                  </span>
                </span>
              ))}
            </p>
          </div>
        )}

        <div className="flex items-center gap-4 mb-6">
          <div className="drawer-qty inline-flex items-center border border-black/20">
            <button
              type="button"
              className="drawer-qty__btn hover:bg-black/5 transition-colors inline-flex items-center justify-center"
              onClick={() => setQty((q) => Math.max(1, q - 1))}
              aria-label={t("a11y.decrease")}
            >
              <IconMinus size={15} />
            </button>
            <span className="drawer-qty__value text-center text-sm">{qty}</span>
            <button
              type="button"
              className="drawer-qty__btn hover:bg-black/5 transition-colors inline-flex items-center justify-center"
              onClick={() =>
                setQty((q) =>
                  activeStock == null ? q + 1 : Math.min(activeStock, q + 1),
                )
              }
              disabled={activeStock != null && qty >= activeStock}
              aria-label={t("a11y.increase")}
            >
              <IconPlus size={15} />
            </button>
          </div>
        </div>

        <div className="product-buy-actions flex flex-wrap items-center gap-3 mb-6">
          <button
            type="button"
            className="btn btn-primary w-full md:w-auto min-w-[220px] disabled:opacity-45 gap-2"
            disabled={!canAdd}
            onClick={handleAdd}
          >
            {!canAdd ? (
              t("product.soldOut")
            ) : added ? (
              <>
                <IconCheck size={14} />
                {t("product.added")}
              </>
            ) : (
              t("product.addToCart")
            )}
          </button>
          <WishlistHeart
            product={cartProduct}
            size="md"
            className="product-detail__wish"
          />
        </div>

        {shortDescription && (
          <div className="mt-8 text-sm leading-relaxed opacity-80 whitespace-pre-line">
            {shortDescription}
          </div>
        )}
        {description &&
          description.trim() !== (shortDescription ?? "").trim() && (
            <div className="mt-3 text-sm leading-relaxed opacity-60 whitespace-pre-line">
              {description}
            </div>
          )}

        <dl className="product-detail__specs mt-8">
          {product.sku ? (
            <div className="product-detail__spec">
              <dt>{t("product.sku")}</dt>
              <dd>{product.sku.replace(/^DW-/, "")}</dd>
            </div>
          ) : null}
          {product.material ? (
            <div className="product-detail__spec">
              <dt>{t("product.material")}</dt>
              <dd>{product.material}</dd>
            </div>
          ) : null}
          {product.categoryName ? (
            <div className="product-detail__spec">
              <dt>{t("product.category")}</dt>
              <dd>
                {product.categorySlug ? (
                  <Link
                    href={`/collections/${product.categorySlug}`}
                    className="link-underline"
                  >
                    {product.categoryName}
                  </Link>
                ) : (
                  product.categoryName
                )}
              </dd>
            </div>
          ) : null}
        </dl>

        <div className="mt-10 text-sm opacity-70 space-y-2">
          <p>{t("product.curated", { brand: brand.name })}</p>
          <Link href="/collections/all" className="link-underline">
            {t("cart.continue")}
          </Link>
        </div>
      </div>
    </div>
  );
}
