"use client";

import dynamic from "next/dynamic";
import {
  Suspense,
  useEffect,
  useState,
  type ReactNode,
} from "react";
import type {
  CheckoutSettings,
  Currency,
  InventorySettings,
  SiteNavItem,
  StoreSocial,
} from "@/lib/api";
import { BrandProvider, type BrandChrome } from "@/context/brand";
import { CartProvider, useCart } from "@/context/cart";
import { WishlistProvider, useWishlist } from "@/context/wishlist";
import { CheckoutSettingsProvider } from "@/context/checkout-settings";
import { InventorySettingsProvider } from "@/context/inventory-settings";
import { CurrencyProvider } from "@/context/currency";
import { LocaleProvider, useLocale } from "@/context/locale";
import type { Locale } from "@/lib/i18n";
import { Header } from "@/components/Header";

function SkipLink() {
  const { t } = useLocale();
  // Hardcoded English fallback keeps the skip link usable even if i18n
  // dictionaries fail to load during a hot reload.
  const label = t("a11y.skip") || "Skip to content";
  return (
    <a href="#main" className="skip-link">
      {label}
    </a>
  );
}

const CartDrawer = dynamic(
  () => import("@/components/CartDrawer").then((m) => m.CartDrawer),
  { ssr: false },
);

const WishlistDrawer = dynamic(
  () => import("@/components/WishlistDrawer").then((m) => m.WishlistDrawer),
  { ssr: false },
);

const CatalogSearchWarmup = dynamic(
  () =>
    import("@/components/CatalogSearchWarmup").then((m) => m.CatalogSearchWarmup),
  { ssr: false },
);

const RouteLoader = dynamic(
  () => import("@/components/RouteLoader").then((m) => m.RouteLoader),
  { ssr: false },
);

function LazyCartDrawer() {
  const { isOpen } = useCart();
  const [ready, setReady] = useState(false);

  useEffect(() => {
    if (isOpen) setReady(true);
  }, [isOpen]);

  if (!ready) return null;
  return <CartDrawer />;
}

function LazyWishlistDrawer() {
  const { isOpen } = useWishlist();
  const [ready, setReady] = useState(false);

  useEffect(() => {
    if (isOpen) setReady(true);
  }, [isOpen]);

  if (!ready) return null;
  return <WishlistDrawer />;
}

function DeferredShellExtras() {
  const [idle, setIdle] = useState(false);

  useEffect(() => {
    let cancelled = false;
    let idleId: number | undefined;
    let timeoutId = 0;

    const arm = () => {
      if (cancelled) return;
      if (typeof window.requestIdleCallback === "function") {
        idleId = window.requestIdleCallback(() => {
          if (!cancelled) setIdle(true);
        }, { timeout: 8000 });
      } else {
        timeoutId = window.setTimeout(() => {
          if (!cancelled) setIdle(true);
        }, 5000);
      }
    };

    if (document.readyState === "complete") {
      arm();
    } else {
      window.addEventListener("load", arm, { once: true });
    }

    return () => {
      cancelled = true;
      window.removeEventListener("load", arm);
      window.clearTimeout(timeoutId);
      if (
        idleId != null &&
        typeof window.cancelIdleCallback === "function"
      ) {
        window.cancelIdleCallback(idleId);
      }
    };
  }, []);

  return (
    <>
      <LazyCartDrawer />
      <LazyWishlistDrawer />
      {idle ? <CatalogSearchWarmup /> : null}
      {idle ? (
        <Suspense fallback={null}>
          <RouteLoader />
        </Suspense>
      ) : null}
    </>
  );
}

export function Providers({
  children,
  footer,
  currency,
  announcement,
  social,
  nav,
  checkout,
  inventory,
  brand,
  locale,
}: {
  children: ReactNode;
  footer: ReactNode;
  currency?: Currency;
  announcement?: string;
  social?: StoreSocial;
  nav?: SiteNavItem[];
  checkout?: CheckoutSettings;
  inventory?: InventorySettings;
  brand: BrandChrome;
  locale: Locale;
}) {
  return (
    <LocaleProvider initialLocale={locale}>
      <BrandProvider value={brand}>
        <CurrencyProvider currency={currency}>
          <InventorySettingsProvider initial={inventory}>
            <CheckoutSettingsProvider initial={checkout}>
              <CartProvider>
                <WishlistProvider>
                  <SkipLink />
                  <div className="min-h-screen flex flex-col">
                    <Header
                      announcement={announcement}
                      social={social}
                      nav={nav}
                    />
                    <main id="main" className="flex-1">
                      {children}
                    </main>
                    {footer}
                  </div>
                  <DeferredShellExtras />
                </WishlistProvider>
              </CartProvider>
            </CheckoutSettingsProvider>
          </InventorySettingsProvider>
        </CurrencyProvider>
      </BrandProvider>
    </LocaleProvider>
  );
}
