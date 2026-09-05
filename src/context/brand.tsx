"use client";

import {
  createContext,
  useContext,
  type ReactNode,
} from "react";
import { BRAND } from "@/lib/brand";

export type BrandChrome = {
  name: string;
  logo: string;
  logoOnDark: string;
};

const BrandContext = createContext<BrandChrome>({
  name: BRAND.name,
  logo: BRAND.logo,
  logoOnDark: BRAND.logoOnDark,
});

export function BrandProvider({
  value,
  children,
}: {
  value: BrandChrome;
  children: ReactNode;
}) {
  return (
    <BrandContext.Provider value={value}>{children}</BrandContext.Provider>
  );
}

export function useBrand(): BrandChrome {
  return useContext(BrandContext);
}
