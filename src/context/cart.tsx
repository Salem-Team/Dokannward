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
import type { Product, ProductColor } from "@/lib/catalog";
import { mapListProduct } from "@/lib/catalog";
import { fetchProduct } from "@/lib/api";
import { toProductCardData } from "@/lib/product-card";
import {
  pickStorefrontImage,
  storefrontImageSrc,
} from "@/lib/media";

export type CartItem = {
  product: Product;
  quantity: number;
  /** Which color variant (if any) the shopper picked before adding to cart. */
  color?: ProductColor;
};

function sanitizeColor(
  color: ProductColor | undefined,
  productImage: string | null,
): ProductColor | undefined {
  if (!color) return undefined;
  const image =
    storefrontImageSrc(color.image) ||
    storefrontImageSrc(productImage) ||
    null;
  return { ...color, image };
}

/** Persist only fields drawers / checkout need — never reviews or SEO. */
function slimCartProduct(product: Product): Product {
  const card = toProductCardData(product);
  const image =
    pickStorefrontImage(card.image, ...(product.images ?? [])) || null;
  const colors = (card.colors ?? []).map(
    (color) => sanitizeColor(color, image)!,
  );

  return {
    ...card,
    image,
    colors,
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

function sanitizeCartItem(item: CartItem): CartItem {
  const product = slimCartProduct(item.product);
  return {
    ...item,
    product,
    color: sanitizeColor(item.color, product.image),
  };
}

/**
 * Re-pull live catalog photos for every cart handle so a renamed product or
 * replaced upload never leaves a broken thumb in checkout localStorage.
 * Fetches only the handles in the cart — never walks the full catalog.
 */
async function refreshCartMedia(items: CartItem[]): Promise<CartItem[]> {
  if (!items.length) return items;

  const handles = Array.from(
    new Set(items.map((item) => item.product.handle).filter(Boolean)),
  );
  const liveRows = await Promise.all(handles.map((handle) => fetchProduct(handle)));
  const byHandle = new Map<string, Product>();
  for (const row of liveRows) {
    if (!row?.slug) continue;
    byHandle.set(row.slug, mapListProduct(row));
  }

  return items.map((item) => {
    const live = byHandle.get(item.product.handle);
    if (!live) return sanitizeCartItem(item);

    const merged: Product = {
      ...item.product,
      title: live.title || item.product.title,
      price: live.price || item.product.price,
      available: live.available,
      image: live.image,
      images: live.image ? [live.image] : item.product.images,
      colors: live.colors?.length ? live.colors : item.product.colors,
      vendor: live.vendor || item.product.vendor,
      brandSlug: live.brandSlug ?? item.product.brandSlug,
    };

    const product = slimCartProduct(merged);
    let color = sanitizeColor(item.color, product.image);

    if (color && live.colors?.length) {
      const match =
        live.colors.find((c) => c.variantId === color!.variantId) ||
        live.colors.find(
          (c) =>
            c.name === color!.name &&
            (!color!.size ||
              c.sizes?.some((s) => s.name === color!.size) ||
              !c.sizes?.length),
        );
      if (match) {
        color = sanitizeColor(
          {
            ...color,
            image: match.image || color.image,
            price: match.price || color.price,
            inStock: match.inStock,
          },
          product.image,
        );
      }
    }

    return { ...item, product, color };
  });
}

/** Cart rows are keyed by product handle + chosen color, so the same bag in
 * two different colors shows up as two separate lines. */
export function lineKey(handle: string, color?: ProductColor) {
  return color ? `${handle}::${color.variantId}` : handle;
}

type CartContextValue = {
  items: CartItem[];
  isOpen: boolean;
  openCart: () => void;
  closeCart: () => void;
  toggleCart: () => void;
  addItem: (product: Product, quantity?: number, color?: ProductColor) => void;
  removeItem: (key: string) => void;
  updateQuantity: (key: string, quantity: number) => void;
  clearCart: () => void;
  count: number;
  subtotal: number;
};

const CartContext = createContext<CartContextValue | null>(null);
const STORAGE_KEY = "dokannward-cart";

function readCart(): CartItem[] {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw) as CartItem[];
    if (!Array.isArray(parsed)) return [];
    return parsed
      .filter(
        (row) => row && row.product && typeof row.product.handle === "string",
      )
      .map((row) => sanitizeCartItem(row));
  } catch {
    return [];
  }
}

function writeCart(items: CartItem[]) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
  } catch {
    /* quota / private mode */
  }
}

export function CartProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<CartItem[]>([]);
  const [isOpen, setIsOpen] = useState(false);
  const [hydrated, setHydrated] = useState(false);

  useEffect(() => {
    const stored = readCart();
    setItems(stored);
    setHydrated(true);

    let cancelled = false;
    void refreshCartMedia(stored).then((next) => {
      if (cancelled) return;
      setItems(next);
    });

    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    if (!hydrated) return;
    writeCart(items);
  }, [items, hydrated]);

  const openCart = useCallback(() => setIsOpen(true), []);
  const closeCart = useCallback(() => setIsOpen(false), []);
  const toggleCart = useCallback(() => setIsOpen((v) => !v), []);

  const addItem = useCallback(
    (product: Product, quantity = 1, color?: ProductColor) => {
      const slim = slimCartProduct(product);
      const lineColor = sanitizeColor(color, slim.image);
      setItems((prev) => {
        const key = lineKey(slim.handle, lineColor);
        const existing = prev.find(
          (i) => lineKey(i.product.handle, i.color) === key,
        );
        if (existing) {
          return prev.map((i) =>
            lineKey(i.product.handle, i.color) === key
              ? { ...i, quantity: i.quantity + quantity }
              : i,
          );
        }
        return [...prev, { product: slim, quantity, color: lineColor }];
      });
      setIsOpen(true);
    },
    [],
  );

  const removeItem = useCallback((key: string) => {
    setItems((prev) =>
      prev.filter((i) => lineKey(i.product.handle, i.color) !== key),
    );
  }, []);

  const updateQuantity = useCallback((key: string, quantity: number) => {
    if (quantity <= 0) {
      setItems((prev) =>
        prev.filter((i) => lineKey(i.product.handle, i.color) !== key),
      );
      return;
    }
    setItems((prev) =>
      prev.map((i) =>
        lineKey(i.product.handle, i.color) === key ? { ...i, quantity } : i,
      ),
    );
  }, []);

  const clearCart = useCallback(() => setItems([]), []);

  const count = useMemo(
    () => items.reduce((sum, i) => sum + i.quantity, 0),
    [items],
  );

  const subtotal = useMemo(
    () =>
      items.reduce(
        (sum, i) =>
          sum + parseFloat(i.color?.price ?? i.product.price) * i.quantity,
        0,
      ),
    [items],
  );

  const value = useMemo(
    () => ({
      items,
      isOpen,
      openCart,
      closeCart,
      toggleCart,
      addItem,
      removeItem,
      updateQuantity,
      clearCart,
      count,
      subtotal,
    }),
    [
      items,
      isOpen,
      openCart,
      closeCart,
      toggleCart,
      addItem,
      removeItem,
      updateQuantity,
      clearCart,
      count,
      subtotal,
    ],
  );

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart() {
  const ctx = useContext(CartContext);
  if (!ctx) throw new Error("useCart must be used within CartProvider");
  return ctx;
}
