"use client";

import Link from "next/link";
import { StorefrontImage } from "@/components/StorefrontImage";
import { useBrand } from "@/context/brand";
import { useWishlist } from "@/context/wishlist";
import { useCurrency } from "@/context/currency";
import { useLocale } from "@/context/locale";
import { IconClose, IconHeartEmpty, IconTrash } from "@/components/Icons";

export function WishlistDrawer() {
  const brand = useBrand();
  const { isOpen, closeWishlist, items, removeItem, count } = useWishlist();
  const { format } = useCurrency();
  const { t, isRtl, locale } = useLocale();

  if (!isOpen) return null;

  return (
    <div className="drawer-shell fixed inset-0 z-[60]" dir={isRtl ? "rtl" : "ltr"}>
      <button
        type="button"
        className="drawer-shell__scrim absolute inset-0"
        aria-label={t("a11y.closeWishlist")}
        onClick={closeWishlist}
      />
      <aside
        key={locale}
        lang={locale}
        className={`drawer-panel${isRtl ? " drawer-panel--start" : ""} absolute ${isRtl ? "left-0" : "right-0"} top-0 bottom-0 w-[min(100vw,420px)] bg-[var(--color-white)] flex flex-col`}
      >
        <div className="drawer-panel__head flex items-center justify-between px-5 py-4 border-b border-black/10">
          <h2 className="text-lg heading">
            {t("wish.title")}
            {count > 0 ? (
              <span className="ms-2 text-sm font-normal opacity-45">
                ({count})
              </span>
            ) : null}
          </h2>
          <button
            type="button"
            onClick={closeWishlist}
            aria-label={t("a11y.close")}
            className="site-header__icon"
          >
            <IconClose size={18} />
          </button>
        </div>

        {items.length === 0 ? (
          <div className="flex-1 flex flex-col items-center justify-center gap-4 px-6 text-center">
            <span className="opacity-20 mb-1">
              <IconHeartEmpty size={40} />
            </span>
            <h3 className="text-xl heading">{t("wish.empty")}</h3>
            <p className="text-sm opacity-60">{t("wish.emptyHint")}</p>
            <Link
              href="/collections/all"
              onClick={closeWishlist}
              className="btn btn-primary mt-2"
            >
              {t("cart.continue")}
            </Link>
          </div>
        ) : (
          <ul className="flex-1 overflow-y-auto px-5 py-4 space-y-5">
            {items.map((product) => (
              <li key={product.handle} className="flex gap-4 animate-fade-up">
                <Link
                  href={`/products/${product.handle}`}
                  onClick={closeWishlist}
                  className="shrink-0"
                >
                  <StorefrontImage
                    src={product.image}
                    fallbacks={[...(product.images ?? []), brand.logo]}
                    alt={product.title}
                    width={88}
                    height={88}
                    className="w-[88px] h-[88px] object-cover bg-[var(--color-paper)] media-mono rounded-md"
                  />
                </Link>
                <div className="flex-1 min-w-0">
                  <div className="flex justify-between gap-2">
                    <Link
                      href={`/products/${product.handle}`}
                      onClick={closeWishlist}
                      className="text-sm font-medium link-underline"
                    >
                      {product.title}
                    </Link>
                    <button
                      type="button"
                      className="drawer-qty__remove opacity-40 hover:opacity-100 transition-opacity"
                      aria-label={t("wish.remove")}
                      onClick={() => removeItem(product.handle)}
                    >
                      <IconTrash size={16} />
                    </button>
                  </div>
                  {product.vendor ? (
                    <p className="mt-1 text-xs opacity-50 uppercase tracking-[0.08em]">
                      {product.vendor}
                    </p>
                  ) : null}
                  <p className="mt-2 text-sm">{format(product.price)}</p>
                  <Link
                    href={`/products/${product.handle}`}
                    onClick={closeWishlist}
                    className="inline-flex mt-3 text-[10px] uppercase tracking-[0.14em] opacity-55 hover:opacity-100 transition-opacity"
                  >
                    {t("wish.view")}
                  </Link>
                </div>
              </li>
            ))}
          </ul>
        )}
      </aside>
    </div>
  );
}
