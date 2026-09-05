"use client";

import { createContext, useContext, useMemo, type ReactNode } from "react";
import { DEFAULT_CURRENCY, type Currency } from "@/lib/api";
import { formatPrice } from "@/lib/catalog";

type CurrencyContextValue = {
  currency: Currency;
  format: (price: string | number) => string;
};

const CurrencyContext = createContext<CurrencyContextValue | null>(null);

/**
 * Wraps the app with whatever currency the admin configured in
 * Settings → Currency (fetched server-side once in the root layout), so
 * every client component can render prices consistently without each one
 * re-fetching the setting.
 */
export function CurrencyProvider({
  currency = DEFAULT_CURRENCY,
  children,
}: {
  currency?: Currency;
  children: ReactNode;
}) {
  const value = useMemo(
    () => ({
      currency,
      format: (price: string | number) => formatPrice(price, currency),
    }),
    [currency],
  );

  return <CurrencyContext.Provider value={value}>{children}</CurrencyContext.Provider>;
}

export function useCurrency() {
  const ctx = useContext(CurrencyContext);
  if (!ctx) throw new Error("useCurrency must be used within CurrencyProvider");
  return ctx;
}
