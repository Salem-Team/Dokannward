"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import type { Product } from "@/lib/catalog";
import { toProductCardData } from "@/lib/product-card";
import { pickStorefrontImage } from "@/lib/media";

type WishlistContextValue = {
  items: Product[];
  isOpen: boolean;
  openWishlist: () => void;
  closeWishlist: () => void;
  toggleWishlist: () => void;
  toggleItem: (product: Product) => void;
  addItem: (product: Product) => void;
  removeItem: (handle: string) => void;
  has: (handle: string) => boolean;
  clear: () => void;
  count: number;
};

const WishlistContext = createContext<WishlistContextValue | null>(null);
const STORAGE_KEY = "dokannward-wishlist";

function slimWishlistProduct(product: Product): Product {
  const card = toProductCardData(product);
  const image =
    pickStorefrontImage(card.image, ...(product.images ?? [])) || null;
  return {
    ...card,
    image,
    compareAtPrice: product.compareAtPrice ?? null,
    featured: false,
    images: image ? [image] : [],
    description: null,
    shortDescription: null,
    material: null,
    updatedAt: null,
    categorySlug: product.categorySlug ?? null,
    ratingAverage: null,
    reviewsCount: 0,
    reviews: [],
    seoTitle: null,
    seoDescription: null,
    seoKeywords: null,
  };
}

function readWishlist(): Product[] {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw) as Product[];
    if (!Array.isArray(parsed)) return [];
    return parsed
      .filter((p) => p && typeof p.handle === "string")
      .map(slimWishlistProduct);
  } catch {
    return [];
  }
}

function writeWishlist(items: Product[]) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
  } catch {
    /* quota / private mode */
  }
}

export function WishlistProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<Product[]>([]);
  const [isOpen, setIsOpen] = useState(false);
  const [hydrated, setHydrated] = useState(false);

  useEffect(() => {
    setItems(readWishlist());
    setHydrated(true);
  }, []);

  useEffect(() => {
    if (!hydrated) return;
    writeWishlist(items);
  }, [items, hydrated]);

  const openWishlist = useCallback(() => setIsOpen(true), []);
  const closeWishlist = useCallback(() => setIsOpen(false), []);
  const toggleWishlist = useCallback(() => setIsOpen((v) => !v), []);

  const has = useCallback(
    (handle: string) => items.some((p) => p.handle === handle),
    [items],
  );

  const addItem = useCallback((product: Product) => {
    const slim = slimWishlistProduct(product);
    setItems((prev) =>
      prev.some((p) => p.handle === slim.handle) ? prev : [slim, ...prev],
    );
  }, []);

  const removeItem = useCallback((handle: string) => {
    setItems((prev) => prev.filter((p) => p.handle !== handle));
  }, []);

  const toggleItem = useCallback((product: Product) => {
    const slim = slimWishlistProduct(product);
    setItems((prev) => {
      const exists = prev.some((p) => p.handle === slim.handle);
      return exists
        ? prev.filter((p) => p.handle !== slim.handle)
        : [slim, ...prev];
    });
  }, []);

  const clear = useCallback(() => setItems([]), []);

  const count = items.length;

  const value = useMemo(
    () => ({
      items,
      isOpen,
      openWishlist,
      closeWishlist,
      toggleWishlist,
      toggleItem,
      addItem,
      removeItem,
      has,
      clear,
      count,
    }),
    [
      items,
      isOpen,
      openWishlist,
      closeWishlist,
      toggleWishlist,
      toggleItem,
      addItem,
      removeItem,
      has,
      clear,
      count,
    ],
  );

  return (
    <WishlistContext.Provider value={value}>{children}</WishlistContext.Provider>
  );
}

export function useWishlist() {
  const ctx = useContext(WishlistContext);
  if (!ctx) throw new Error("useWishlist must be used within WishlistProvider");
  return ctx;
}
