import type { Product, ProductColor } from "@/lib/catalog";

/**
 * Slim PDP payload for the interactive buy box / gallery.
 * Strips reviews, SEO, and long copy — those stay in the RSC tree.
 */
export type ProductDetailClientData = Pick<
  Product,
  | "id"
  | "sku"
  | "handle"
  | "title"
  | "vendor"
  | "brandSlug"
  | "categorySlug"
  | "categoryName"
  | "collections"
  | "price"
  | "compareAtPrice"
  | "available"
  | "stock"
  | "featured"
  | "image"
  | "images"
  | "colors"
  | "sizes"
  | "ratingAverage"
  | "reviewsCount"
  | "material"
  | "updatedAt"
>;

/** Stable gallery order: primary first, then remaining unique URLs. */
export function productGalleryImages(product: {
  image: string | null;
  images: string[];
}): string[] {
  const seen = new Set<string>();
  const out: string[] = [];
  for (const src of [product.image, ...(product.images ?? [])]) {
    const url = typeof src === "string" ? src.trim() : "";
    if (!url || seen.has(url)) continue;
    seen.add(url);
    out.push(url);
  }
  return out;
}

export function toProductDetailClientData(
  product: Product,
): ProductDetailClientData {
  return {
    id: product.id,
    sku: product.sku,
    handle: product.handle,
    title: product.title,
    vendor: product.vendor,
    brandSlug: product.brandSlug,
    categorySlug: product.categorySlug,
    categoryName: product.categoryName,
    collections: product.collections,
    price: product.price,
    compareAtPrice: product.compareAtPrice,
    available: product.available,
    stock: product.stock,
    featured: product.featured,
    image: product.image,
    images: productGalleryImages(product),
    colors: product.colors,
    sizes: product.sizes ?? [],
    ratingAverage: product.ratingAverage,
    reviewsCount: product.reviewsCount,
    material: product.material,
    updatedAt: product.updatedAt,
  };
}

/** Expand slim PDP data into a cart/wishlist-compatible Product shape. */
export function toCartProduct(product: ProductDetailClientData): Product {
  return {
    ...product,
    images: product.images?.length
      ? product.images
      : product.image
        ? [product.image]
        : [],
    description: null,
    shortDescription: null,
    reviews: [],
    seoTitle: null,
    seoDescription: null,
    seoKeywords: null,
  };
}

export type { ProductColor };
