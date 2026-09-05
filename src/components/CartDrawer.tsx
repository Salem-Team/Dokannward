"use client";

import Link from "next/link";
import { StorefrontImage } from "@/components/StorefrontImage";
import { useBrand } from "@/context/brand";
import { useCart, lineKey } from "@/context/cart";
import { useCheckoutSettings } from "@/context/checkout-settings";
import { useCurrency } from "@/context/currency";
import { formatCheckoutMessage } from "@/lib/api";
import {
  IconBagEmpty,
  IconClose,
  IconMinus,
  IconPlus,
  IconTrash,
} from "@/components/Icons";
import { useLocale } from "@/context/locale";

export function CartDrawer() {
  const {
    isOpen,
    closeCart,
    items,
    removeItem,
    updateQuantity,
    subtotal,
  } = useCart();
  const { format } = useCurrency();
  const brand = useBrand();
  const { settings: checkout } = useCheckoutSettings();
  const { t, isRtl, locale } = useLocale();

  if (!isOpen) return null;

  const taxMessage = checkout.tax_enabled
    ? formatCheckoutMessage(
        checkout.tax_enabled_message,
        t("cart.taxOn"),
        { rate: checkout.tax_rate },
      )
    : formatCheckoutMessage(
        checkout.tax_disabled_message,
        t("cart.taxOff"),
        {},
      );

  return (
    <div className="drawer-shell fixed inset-0 z-[60]" dir={isRtl ? "rtl" : "ltr"}>
      <button
        type="button"
        className="drawer-shell__scrim absolute inset-0"
        aria-label={t("a11y.closeCart")}
        onClick={closeCart}
      />
      <aside
        key={locale}
        lang={locale}
        className={`drawer-panel${isRtl ? " drawer-panel--start" : ""} absolute ${isRtl ? "left-0" : "right-0"} top-0 bottom-0 w-[min(100vw,420px)] bg-[var(--color-white)] flex flex-col`}
      >
        <div className="drawer-panel__head flex items-center justify-between px-5 py-4 border-b border-black/10">
          <h2 className="text-lg heading">{t("cart.title")}</h2>
          <button
            type="button"
            onClick={closeCart}
            aria-label={t("a11y.close")}
            className="site-header__icon"
          >
            <IconClose size={18} />
          </button>
        </div>

        {items.length === 0 ? (
          <div className="flex-1 flex flex-col items-center justify-center gap-4 px-6 text-center">
            <span className="opacity-20 mb-1">
              <IconBagEmpty size={40} />
            </span>
            <h3 className="text-xl heading">{t("cart.empty")}</h3>
            <p className="text-sm opacity-60">{t("cart.emptyHint")}</p>
            <Link
              href="/collections/all"
              onClick={closeCart}
              className="btn btn-primary mt-2"
            >
              {t("cart.continue")}
            </Link>
          </div>
        ) : (
          <>
            <ul className="flex-1 overflow-y-auto px-5 py-4 space-y-5">
              {items.map(({ product, quantity, color }) => {
                const key = lineKey(product.handle, color);
                return (
                  <li key={key} className="flex gap-4 animate-fade-up">
                    <Link href={`/products/${product.handle}`} onClick={closeCart} className="shrink-0">
                      <StorefrontImage
                        src={color?.image || product.image}
                        fallbacks={[...(product.images ?? []), brand.logo]}
                        alt={product.title}
                        width={88}
                        height={88}
                        className="w-[88px] h-[88px] object-cover bg-[var(--color-paper)] media-mono"
                      />
                    </Link>
                    <div className="flex-1 min-w-0">
                      <div className="flex justify-between gap-2">
                        <Link href={`/products/${product.handle}`} onClick={closeCart} className="text-sm font-medium link-underline">
                          {product.title}
                        </Link>
                        <button
                          type="button"
                          className="drawer-qty__remove opacity-40 hover:opacity-100 transition-opacity"
                          aria-label={t("a11y.remove")}
                          onClick={() => removeItem(key)}
                        >
                          <IconTrash size={16} />
                        </button>
                      </div>
                      {color && (
                        <p className="mt-1 flex items-center gap-1.5 text-xs opacity-60">
                          <span
                            className="w-3 h-3 rounded-full border border-black/10 shrink-0"
                            style={{ backgroundColor: color.hex }}
                          />
                          {color.name}
                          {color.size ? ` · ${color.size}` : ""}
                        </p>
                      )}
                      <p className="text-sm mt-1">{format(color?.price ?? product.price)}</p>
                      <div className="drawer-qty mt-3 inline-flex items-center border border-black/20">
                        <button
                          type="button"
                          className="drawer-qty__btn hover:bg-black/5 transition-colors inline-flex items-center justify-center"
                          onClick={() => updateQuantity(key, quantity - 1)}
                          aria-label={t("a11y.decrease")}
                        >
                          <IconMinus size={14} />
                        </button>
                        <span className="drawer-qty__value text-center text-sm">{quantity}</span>
                        <button
                          type="button"
                          className="drawer-qty__btn hover:bg-black/5 transition-colors inline-flex items-center justify-center"
                          onClick={() => updateQuantity(key, quantity + 1)}
                          aria-label={t("a11y.increase")}
                        >
                          <IconPlus size={14} />
                        </button>
                      </div>
                    </div>
                  </li>
                );
              })}
            </ul>
            <div className="drawer-panel__foot border-t border-black/10 px-5 pt-4 space-y-3">
              <div className="flex justify-between text-sm">
                <span>{t("cart.subtotal")}</span>
                <span className="font-medium">{format(subtotal)}</span>
              </div>
              <div className="flex justify-between gap-3 text-xs opacity-60">
                <span>
                  {t("cart.shipping")}
                  {checkout.shipping_company
                    ? ` · ${checkout.shipping_company}`
                    : ""}
                </span>
                <span className="shrink-0">
                  {format(checkout.standard_shipping_fee)}
                </span>
              </div>
              <p className="text-xs opacity-45">{taxMessage}</p>
              <Link
                href="/checkout"
                prefetch={false}
                onClick={closeCart}
                className="btn btn-primary w-full"
              >
                {t("cart.checkout")}
              </Link>
              <Link
                href="/collections/all"
                onClick={closeCart}
                className="block text-center text-sm link-underline opacity-70 hover:opacity-100"
              >
                {t("cart.continue")}
              </Link>
            </div>
          </>
        )}

        <div className="drawer-panel__legal px-5 pb-4 text-[11px] opacity-40 text-center">
          {t("cart.footer", { brand: brand.name })}
        </div>
      </aside>
    </div>
  );
}
