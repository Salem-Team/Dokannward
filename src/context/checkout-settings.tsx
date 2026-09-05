"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from "react";
import {
  DEFAULT_CHECKOUT_SETTINGS,
  loadCheckoutSettings,
  type CheckoutSettings,
} from "@/lib/api";

type CheckoutSettingsContextValue = {
  settings: CheckoutSettings;
  /** True once the browser has confirmed the values against the admin API. */
  isLive: boolean;
};

const CheckoutSettingsContext =
  createContext<CheckoutSettingsContextValue | null>(null);

/** Rare background check — admin shipping/tax edits are infrequent; focus/visibility covers the hot path. */
const POLL_MS = 5 * 60_000;

function sameCheckoutSettings(a: CheckoutSettings, b: CheckoutSettings): boolean {
  return (
    a.standard_shipping_fee === b.standard_shipping_fee &&
    a.shipping_company === b.shipping_company &&
    a.tax_rate === b.tax_rate &&
    a.tax_enabled === b.tax_enabled &&
    a.tax_enabled_message === b.tax_enabled_message &&
    a.tax_disabled_message === b.tax_disabled_message &&
    a.default_payment_method === b.default_payment_method &&
    JSON.stringify(a.payment_methods) === JSON.stringify(b.payment_methods)
  );
}

/**
 * Shipping and tax decide what a shopper actually pays, so they must not be
 * read from a cached render. A server value is fine for first paint, but the
 * App Router keeps both the layout shell and prefetched pages in a client-side
 * cache (`experimental.staleTimes`), which can outlive an admin edit.
 *
 * The browser therefore re-confirms the settings on mount, whenever the tab is
 * focused again, and on a short poll while the tab is visible — keeping the
 * last known-good copy if the API is unreachable.
 */
export function CheckoutSettingsProvider({
  initial = DEFAULT_CHECKOUT_SETTINGS,
  children,
}: {
  initial?: CheckoutSettings;
  children: ReactNode;
}) {
  const [live, setLive] = useState<CheckoutSettings | null>(null);
  const inFlight = useRef(false);
  const latest = useRef<CheckoutSettings | null>(null);

  const revalidate = useCallback(async () => {
    if (inFlight.current) return;
    if (typeof document !== "undefined" && document.visibilityState === "hidden") {
      return;
    }
    inFlight.current = true;
    try {
      const next = await loadCheckoutSettings({ fresh: true });
      if (!next) return;
      if (latest.current && sameCheckoutSettings(latest.current, next)) return;
      latest.current = next;
      setLive(next);
    } finally {
      inFlight.current = false;
    }
  }, []);

  useEffect(() => {
    void revalidate();

    const onVisible = () => {
      if (document.visibilityState === "visible") void revalidate();
    };

    const onPageShow = () => void revalidate();
    const onFocus = () => void revalidate();

    document.addEventListener("visibilitychange", onVisible);
    window.addEventListener("pageshow", onPageShow);
    window.addEventListener("focus", onFocus);
    const poll = window.setInterval(() => void revalidate(), POLL_MS);

    return () => {
      document.removeEventListener("visibilitychange", onVisible);
      window.removeEventListener("pageshow", onPageShow);
      window.removeEventListener("focus", onFocus);
      window.clearInterval(poll);
    };
  }, [revalidate]);

  const value = useMemo(
    () => ({ settings: live ?? initial, isLive: live !== null }),
    [live, initial],
  );

  return (
    <CheckoutSettingsContext.Provider value={value}>
      {children}
    </CheckoutSettingsContext.Provider>
  );
}

export function useCheckoutSettings() {
  const ctx = useContext(CheckoutSettingsContext);
  if (!ctx) {
    throw new Error(
      "useCheckoutSettings must be used within CheckoutSettingsProvider",
    );
  }
  return ctx;
}
