import type { Product } from "@/lib/catalog";

/** Slim shape hydrated on the client — no descriptions / reviews / SEO. */
export type ProductCardData = Pick<
  Product,
  | "id"
  | "handle"
  | "title"
  | "price"
  | "compareAtPrice"
  | "available"
  | "image"
  | "colors"
  | "vendor"
  | "brandSlug"
  | "sku"
>;

export function toProductCardData(product: Product): ProductCardData {
  return {
    id: product.id,
    handle: product.handle,
    title: product.title,
    price: product.price,
    compareAtPrice: product.compareAtPrice,
    available: product.available,
    image: product.image,
    colors: product.colors,
    vendor: product.vendor,
    brandSlug: product.brandSlug,
    sku: product.sku,
  };
}
