/**
 * The storefront's product catalog + collections index, sourced live from the
 * Dokan Ward admin (Laravel) backend. Collections and categories created in `/admin`
 * appear on `/collections` automatically — no hardcoded slug allowlists.
 *
 * Hierarchy: Collection → Category → Product (brand is chosen on the product).
 */
import { cache } from "react";
import {
  fetchBrands,
  fetchCategories,
  fetchCurrency,
  fetchProduct,
  fetchProducts,
  fetchStoreSettings,
  fetchCheckoutSettings,
  fetchInventorySettings,
  fetchSiteContent,
  type ApiBrand,
  type ApiCategory,
  type ApiColor,
  type ApiProduct,
  type ApiReview,
  type Currency,
  type StoreSettings,
  type CheckoutSettings,
  type InventorySettings,
  type SiteContent,
  DEFAULT_CURRENCY,
} from "@/lib/api";
import {
  pickFeaturedStorefrontCategories,
  pickHomepageCategoryLogos,
  pickStorefrontCategoryChips,
} from "@/lib/homepageCategories";
import { pickStorefrontImage, storefrontImageSrc } from "@/lib/media";

export type ProductColorSize = {
  name: string;
  variantId: string;
  sku: string;
  price: string;
  inStock: boolean;
  stock?: number | null;
};

export type ProductColor = {
  variantId: string;
  sku: string;
  name: string;
  hex: string;
  price: string;
  compareAtPrice?: string | null;
  inStock: boolean;
  stock?: number | null;
  image: string | null;
  /** Every photo uploaded for this color, primary first. */
  images?: string[];
  /** Per-size purchasable options for this color. Empty when the product has no sizes. */
  sizes?: ProductColorSize[];
  /** Selected size name once the shopper (or cart) locks a size variant. */
  size?: string | null;
};

export type ProductReview = {
  id: string;
  author: string;
  rating: number;
  title: string | null;
  body: string | null;
  recommended: boolean | null;
  createdAt: string;
};

export type ProductCollection = {
  id: string;
  name: string;
  slug: string;
};

export type Product = {
  id: string;
  sku: string;
  handle: string;
  title: string;
  vendor: string;
  brandSlug: string | null;
  categorySlug: string | null;
  categoryName?: string | null;
  collections?: ProductCollection[];
  price: string;
  compareAtPrice: string | null;
  available: boolean;
  stock?: number | null;
  featured: boolean;
  image: string | null;
  images: string[];
  description: string | null;
  shortDescription: string | null;
  material: string | null;
  updatedAt: string | null;
  colors: ProductColor[];
  /** Offered size names for the PDP picker (detail only). */
  sizes?: string[];
  ratingAverage: number | null;
  reviewsCount: number;
  reviews: ProductReview[];
  seoTitle: string | null;
  seoDescription: string | null;
  seoKeywords: string | null;
};

/** Minimal fields the header search dialog needs — keeps layout RSC payload tiny. */
export type SearchProduct = {
  handle: string;
  title: string;
  vendor: string;
  image: string | null;
  price: string;
};

export function toSearchProduct(p: Product): SearchProduct {
  return {
    handle: p.handle,
    title: p.title,
    vendor: p.vendor,
    image: p.image,
    price: p.price,
  };
}

export type StorefrontCollection = {
  handle: string;
  title: string;
  display_title: string;
  image: string | null;
  kind: "brand" | "category" | "collection" | "catalog";
  products_count: number;
  description?: string | null;
};

/** Legacy scraped handles → live admin slugs (so old links keep working). */
const LEGACY_HANDLE_ALIASES: Record<string, string> = {
  "handbags-and-accessories-example-products": "bags",
  frontpage: "all",
};

function mapColor(c: ApiColor): ProductColor {
  const sizes = Array.isArray(c.sizes)
    ? c.sizes
        .filter((s) => s && typeof s.name === "string" && s.variant_id)
        .map((s) => ({
          name: s.name,
          variantId: s.variant_id,
          sku: s.sku,
          price: String(s.price),
          inStock: Boolean(s.in_stock),
          stock: typeof s.stock === "number" ? Math.max(0, s.stock) : null,
        }))
    : [];

  return {
    variantId: c.variant_id,
    sku: c.sku,
    name: c.name,
    hex: c.hex || "#cccccc",
    price: String(c.price),
    compareAtPrice:
      c.compare_at_price != null ? String(c.compare_at_price) : null,
    inStock: c.in_stock,
    stock: typeof c.stock === "number" ? Math.max(0, c.stock) : null,
    image: pickStorefrontImage(c.image, ...(c.images ?? [])),
    images: (() => {
      const seen = new Set<string>();
      const out: string[] = [];
      for (const raw of [c.image, ...(c.images ?? [])]) {
        const src = storefrontImageSrc(raw);
        if (!src || seen.has(src)) continue;
        seen.add(src);
        out.push(src);
      }
      return out;
    })(),
    sizes,
  };
}

function mapReview(r: ApiReview): ProductReview {
  return {
    id: r.id,
    author: r.author,
    rating: r.rating,
    title: r.title,
    body: r.body,
    recommended: r.recommended,
    createdAt: r.created_at,
  };
}

export function mapProduct(p: ApiProduct): Product {
  return {
    id: p.id,
    sku: p.sku,
    handle: p.slug,
    title: p.name,
    vendor: p.brand?.name || "Dokan Ward",
    brandSlug: p.brand?.slug ?? null,
    categorySlug: p.category?.slug ?? null,
    categoryName: p.category?.name ?? null,
    collections: (p.collections ?? [])
      .filter((collection) => Boolean(collection?.slug))
      .map((collection) => ({
        id: collection.id,
        name: collection.name,
        slug: collection.slug!,
      })),
    price: String(p.price),
    compareAtPrice: p.compare_at_price != null ? String(p.compare_at_price) : null,
    available: p.in_stock,
    stock: typeof p.stock === "number" ? Math.max(0, p.stock) : null,
    featured: p.featured,
    image: pickStorefrontImage(p.image, ...(p.images ?? [])),
    images: (() => {
      const seen = new Set<string>();
      const out: string[] = [];
      for (const raw of [p.image, ...(p.images ?? [])]) {
        const src = storefrontImageSrc(raw);
        if (!src || seen.has(src)) continue;
        seen.add(src);
        out.push(src);
      }
      return out;
    })(),
    description: p.description ?? null,
    shortDescription: p.short_description ?? null,
    material: p.material ?? null,
    updatedAt: p.updated_at ?? null,
    colors: (p.colors || []).map(mapColor),
    sizes: Array.isArray(p.sizes)
      ? p.sizes.filter((s): s is string => typeof s === "string" && s.trim() !== "")
      : [],
    ratingAverage: p.rating?.average ?? null,
    reviewsCount: p.rating?.count ?? 0,
    reviews: (p.reviews || []).map(mapReview),
    seoTitle: p.seo?.title ?? null,
    seoDescription: p.seo?.description ?? null,
    seoKeywords: p.seo?.keywords ?? null,
  };
}

/** List/index mapping — drops reviews, SEO, and long copy for faster ISR + search. */
export function mapListProduct(p: ApiProduct): Product {
  const colors = (p.colors || []).map(mapColor);
  // Admin "Main" (is_primary) arrives as `image` — it must win on cards/collections.
  const listingImage = pickStorefrontImage(
    p.image,
    ...(p.images ?? []),
    ...colors.flatMap((color) => [color.image, ...(color.images ?? [])]),
  );

  return {
    id: p.id,
    sku: p.sku,
    handle: p.slug,
    title: p.name,
    vendor: p.brand?.name || "Dokan Ward",
    brandSlug: p.brand?.slug ?? null,
    categorySlug: p.category?.slug ?? null,
    categoryName: p.category?.name ?? null,
    collections: (p.collections ?? [])
      .filter((collection) => Boolean(collection?.slug))
      .map((collection) => ({
        id: collection.id,
        name: collection.name,
        slug: collection.slug!,
      })),
    price: String(p.price),
    compareAtPrice: p.compare_at_price != null ? String(p.compare_at_price) : null,
    available: p.in_stock,
    stock: null,
    featured: p.featured,
    image: listingImage,
    images: listingImage ? [listingImage] : [],
    description: null,
    shortDescription: null,
    material: null,
    updatedAt: p.updated_at ?? null,
    colors,
    sizes: [],
    ratingAverage: p.rating?.average ?? null,
    reviewsCount: p.rating?.count ?? 0,
    reviews: [],
    seoTitle: null,
    seoDescription: null,
    seoKeywords: null,
  };
}

function brandName(b: ApiBrand) {
  return b.name || b.translated_name || "Brand";
}

function flattenCategories(roots: ApiCategory[]): ApiCategory[] {
  const out: ApiCategory[] = [];
  const walk = (nodes: ApiCategory[]) => {
    for (const node of nodes) {
      out.push(node);
      if (node.children?.length) walk(node.children);
    }
  };
  walk(roots);
  return out;
}

function coverFor(_handle: string, preferred?: string | null): string | null {
  // Admin / API media only — never invent theme stock plates.
  const cleaned = preferred?.trim();
  if (!cleaned) return null;
  if (cleaned.includes("ui-avatars.com")) return null;
  if (cleaned.includes("handbags-and-accessories-natural")) return null;
  return cleaned;
}

type CatalogIndex = {
  brands: ApiBrand[];
  categoryRoots: ApiCategory[];
  categoriesFlat: ApiCategory[];
  categorySlugs: Set<string>;
  collectionSlugs: Set<string>;
  brandSlugs: Set<string>;
  /** parent slug → descendant slugs (inclusive) */
  categoryDescendants: Map<string, string[]>;
  collections: StorefrontCollection[];
  byHandle: Map<string, StorefrontCollection>;
};

function buildDescendantMap(roots: ApiCategory[]): Map<string, string[]> {
  const map = new Map<string, string[]>();
  const walk = (node: ApiCategory): string[] => {
    const slug = node.slug;
    if (!slug) return [];
    const kids = (node.children || []).flatMap(walk);
    const all = [slug, ...kids];
    map.set(slug, all);
    return all;
  };
  for (const root of roots) walk(root);
  return map;
}

/** Deduped per-request — product pages reuse the same catalog fetch. */
export const getAllProducts = cache(async (): Promise<Product[]> => {
  const products = await fetchProducts({ perPage: 100 });
  const seen = new Set<string>();
  const unique: Product[] = [];
  for (const raw of products) {
    const mapped = mapListProduct(raw);
    const key = mapped.id || mapped.handle;
    if (!key || seen.has(key)) continue;
    seen.add(key);
    unique.push(mapped);
  }
  return unique;
});

/** Home arrivals rail — one slim page, not the full catalog walk. */
export const getFeaturedProducts = cache(async (): Promise<Product[]> => {
  const mapUnique = (rows: Awaited<ReturnType<typeof fetchProducts>>) => {
    const seen = new Set<string>();
    const unique: Product[] = [];
    for (const raw of rows) {
      const mapped = mapListProduct(raw);
      const key = mapped.id || mapped.handle;
      if (!key || seen.has(key)) continue;
      seen.add(key);
      unique.push(mapped);
    }
    return unique;
  };

  // Prefer featured; fall back only when the featured set is empty.
  const featured = mapUnique(
    await fetchProducts({ featured: true, perPage: 12, allPages: false }),
  );
  if (featured.length > 0) return featured.slice(0, 12);

  return mapUnique(
    await fetchProducts({ perPage: 12, allPages: false }),
  ).slice(0, 12);
});

/** Build brands/categories index; pass products only when counts are required. */
function buildCatalogIndex(
  brands: ApiBrand[],
  categoryRoots: ApiCategory[],
  products: Product[],
): CatalogIndex {
  const categoriesFlat = flattenCategories(categoryRoots);
  const categorySlugs = new Set(
    categoriesFlat.map((c) => c.slug).filter((s): s is string => Boolean(s)),
  );
  const brandSlugs = new Set(
    brands.map((b) => b.slug).filter((s): s is string => Boolean(s)),
  );
  const categoryDescendants = buildDescendantMap(categoryRoots);

  const directCounts: Record<string, number> = {};
  for (const p of products) {
    if (p.brandSlug) directCounts[p.brandSlug] = (directCounts[p.brandSlug] || 0) + 1;
    if (p.categorySlug) {
      directCounts[p.categorySlug] = (directCounts[p.categorySlug] || 0) + 1;
    }
    for (const collection of p.collections ?? []) {
      directCounts[collection.slug] = (directCounts[collection.slug] || 0) + 1;
    }
  }

  const countForCategory = (slug: string) => {
    const tree = categoryDescendants.get(slug) || [slug];
    return tree.reduce((sum, s) => sum + (directCounts[s] || 0), 0);
  };

  const rootHandles = new Set(
    categoryRoots.map((c) => c.slug).filter((s): s is string => Boolean(s)),
  );

  const categoryCollections: StorefrontCollection[] = categoriesFlat
    .filter((c) => c.slug)
    .map((c) => {
      const handle = c.slug as string;
      const isRoot = rootHandles.has(handle);
      // Collection roots from the API set collection_id to their own id.
      // Standalone categories (no collection) may also appear as roots.
      const isCollectionRoot =
        isRoot && c.collection_id != null && String(c.collection_id) === String(c.id);
      return {
        handle,
        title: c.name,
        display_title: c.name,
        image: coverFor(handle, c.image),
        kind: (isCollectionRoot ? "collection" : "category") as "collection" | "category",
        products_count: isCollectionRoot
          ? directCounts[handle] || 0
          : countForCategory(handle),
        description: c.description ?? null,
      };
    });

  const brandCollectionsLive: StorefrontCollection[] = brands
    .filter((b) => b.slug)
    .map((b) => {
      const handle = b.slug as string;
      return {
        handle,
        title: brandName(b),
        display_title: brandName(b),
        image: coverFor(handle, b.logo_url),
        kind: "brand" as const,
        products_count: directCounts[handle] || 0,
        description: b.description ?? null,
      };
    });

  // Index grid for /collections: collection roots only (brands live under /brands).
  const indexCollections = categoryCollections.filter(
    (c) => rootHandles.has(c.handle) && c.kind === "collection",
  );
  const collectionSlugs = new Set(indexCollections.map((c) => c.handle));

  const byHandle = new Map<string, StorefrontCollection>();
  for (const c of [...categoryCollections, ...brandCollectionsLive]) {
    const existing = byHandle.get(c.handle);
    // Collection root + leaf category can share a slug in admin (e.g. Bags/bags).
    // Keep the richer row: prefer a cover image, and never drop a higher product count.
    if (existing) {
      byHandle.set(c.handle, {
        ...existing,
        ...c,
        kind:
          existing.kind === "collection" || c.kind === "collection"
            ? "collection"
            : c.kind,
        title: existing.kind === "collection" ? existing.title : c.title,
        display_title:
          existing.kind === "collection"
            ? existing.display_title
            : c.display_title,
        image: c.image || existing.image,
        products_count: Math.max(existing.products_count, c.products_count),
        description: c.description || existing.description,
      });
      continue;
    }
    byHandle.set(c.handle, c);
  }
  byHandle.set("all", {
    handle: "all",
    title: "Catalog",
    display_title: "New In",
    image: null,
    kind: "catalog",
    products_count: products.length,
  });

  return {
    brands,
    categoryRoots,
    categoriesFlat,
    categorySlugs,
    collectionSlugs,
    brandSlugs,
    categoryDescendants,
    collections: indexCollections,
    byHandle,
  };
}

/**
 * Lightweight homepage/nav index — brands + categories only.
 * Skips the full product walk so Home never waits on every catalog page.
 */
export const getCatalogShell = cache(async (): Promise<CatalogIndex> => {
  const [brands, categoryRoots] = await Promise.all([
    fetchBrands(),
    fetchCategories(),
  ]);
  return buildCatalogIndex(brands, categoryRoots, []);
});

/** Deduped per-request catalog index (brands + categories + counts). */
export const getCatalogIndex = cache(async (): Promise<CatalogIndex> => {
  const [brands, categoryRoots, products] = await Promise.all([
    fetchBrands(),
    fetchCategories(),
    getAllProducts(),
  ]);
  return buildCatalogIndex(brands, categoryRoots, products);
});

/**
 * Resolve a URL handle to the live collection/category/brand API filter.
 * Unknown handles fall through as brand (keeps old brand URLs working even if
 * the brands endpoint briefly fails) — collection pages still 404 via meta.
 */
export async function resolveCollection(
  handle: string,
): Promise<{ brand?: string; category?: string; collection?: string }> {
  const canonical = LEGACY_HANDLE_ALIASES[handle] || handle;
  if (canonical === "all") return {};

  const index = await getCatalogIndex();
  if (index.collectionSlugs.has(canonical)) return { collection: canonical };
  if (index.categorySlugs.has(canonical)) return { category: canonical };
  if (index.brandSlugs.has(canonical)) return { brand: canonical };

  // Soft fallback: treat as brand slug so a brand just created still works
  // before the category set is warm.
  return { brand: canonical };
}

export const getCollectionProductList = cache(async (handle: string): Promise<Product[]> => {
  const canonical = LEGACY_HANDLE_ALIASES[handle] || handle;
  if (canonical === "all") return getAllProducts();

  // Filter the already-cached full catalog in memory — avoids a second
  // /products?brand|category=… round-trip on every collection page.
  const [index, all] = await Promise.all([getCatalogIndex(), getAllProducts()]);

  if (index.collectionSlugs.has(canonical)) {
    return all.filter((p) =>
      p.collections?.some((collection) => collection.slug === canonical),
    );
  }

  if (index.categorySlugs.has(canonical)) {
    const tree = new Set(index.categoryDescendants.get(canonical) || [canonical]);
    return all.filter((p) => p.categorySlug != null && tree.has(p.categorySlug));
  }

  if (index.brandSlugs.has(canonical)) {
    return all.filter((p) => p.brandSlug === canonical);
  }

  const asBrand = all.filter((p) => p.brandSlug === canonical);
  if (asBrand.length > 0) return asBrand;

  const products = await fetchProducts({ brand: canonical, perPage: 100 });
  return products.map(mapListProduct);
});

export const getProductByHandle = cache(async (handle: string): Promise<Product | null> => {
  const product = await fetchProduct(handle);
  return product ? mapProduct(product) : null;
});

export type RelatedProductGroup = {
  kind: "collection" | "category";
  name: string;
  href: string | null;
  products: Product[];
};

export type RelatedProductGroups = {
  collection: RelatedProductGroup | null;
  category: RelatedProductGroup | null;
};

export function prioritizeRelatedProducts(
  product: Product,
  collectionRows: ApiProduct[],
  categoryRows: ApiProduct[],
  limit = 4,
): RelatedProductGroups {
  const primaryCollection = product.collections?.[0] ?? null;
  const seen = new Set([product.handle]);
  const takeUnique = (rows: ApiProduct[]) => {
    const result: Product[] = [];
    for (const row of rows) {
      if (seen.has(row.slug)) continue;
      seen.add(row.slug);
      result.push(mapListProduct(row));
      if (result.length >= limit) break;
    }
    return result;
  };

  // Collection consumes the shared `seen` set first by design.
  const collectionProducts = takeUnique(collectionRows);
  const categoryProducts = takeUnique(categoryRows);

  return {
    collection:
      primaryCollection && collectionProducts.length
        ? {
            kind: "collection",
            name: primaryCollection.name,
            href: `/collections/${primaryCollection.slug}`,
            products: collectionProducts,
          }
        : null,
    category:
      product.categorySlug && categoryProducts.length
        ? {
            kind: "category",
            name: product.categoryName || "",
            href: `/collections/${product.categorySlug}`,
            products: categoryProducts,
          }
        : null,
  };
}

/**
 * PDP discovery priority:
 * 1) direct members of the product's primary published collection;
 * 2) products sharing its independent category.
 *
 * Results are deduplicated so a product never appears in both rails.
 */
export const getRelatedProducts = cache(
  async (product: Product, limit = 4): Promise<RelatedProductGroups> => {
    const primaryCollection = product.collections?.[0] ?? null;
    const [collectionRows, categoryRows] = await Promise.all([
      primaryCollection
        ? fetchProducts({
            collection: primaryCollection.slug,
            perPage: limit + 6,
            allPages: false,
          })
        : Promise.resolve([]),
      product.categorySlug
        ? fetchProducts({
            category: product.categorySlug,
            perPage: limit + 8,
            allPages: false,
          })
        : Promise.resolve([]),
    ]);

    return prioritizeRelatedProducts(
      product,
      collectionRows,
      categoryRows,
      limit,
    );
  },
);

export async function searchProducts(query: string): Promise<Product[]> {
  if (!query.trim()) return getAllProducts();
  const products = await fetchProducts({ q: query, perPage: 50 });
  return products.map(mapListProduct);
}

/** Canonical storefront path for a brand / collection / category / catalog tile. */
export function hrefForStorefrontCollection(
  c: Pick<StorefrontCollection, "handle" | "kind">,
): string {
  if (c.kind === "brand") return `/brands/${c.handle}`;
  if (c.kind === "catalog" || c.handle === "all") return "/collections/all";
  return `/collections/${c.handle}`;
}

/** Collections grid for /collections — every published collection root from admin. */
export const getStorefrontCollections = cache(async (): Promise<StorefrontCollection[]> => {
  const index = await getCatalogIndex();
  return index.collections.map((c) => {
    if (c.image?.trim()) return c;
    // Fall back to the first child category cover so empty-looking roots still show.
    const root = index.categoryRoots.find((r) => r.slug === c.handle);
    const childCover =
      root?.children?.find((ch) => ch.image?.trim())?.image?.trim() ||
      root?.children?.find((ch) => ch.logo_url?.trim())?.logo_url?.trim() ||
      null;
    return childCover ? { ...c, image: childCover } : c;
  });
});

/**
 * /collections chip row — every real category from admin (Bags, Sunglasses,
 * shoes, Homewear, …), not only synthetic collection wrappers.
 */
export const getStorefrontCategoryChips = cache(
  async (): Promise<
    Array<{
      title: string;
      handle: string;
      href: string;
      products_count: number;
    }>
  > => {
    const index = await getCatalogIndex();
    const counts = new Map<string, number>();
    for (const [handle, col] of index.byHandle) {
      counts.set(handle, col.products_count);
    }
    return pickStorefrontCategoryChips(index.categoriesFlat, counts);
  },
);

/** Brands grid for /brands — every house from admin (including empty / coming soon). */
export const getStorefrontBrands = cache(async (): Promise<StorefrontCollection[]> => {
  const index = await getCatalogIndex();
  return Array.from(index.byHandle.values())
    .filter((c) => c.kind === "brand")
    .sort((a, b) => a.title.localeCompare(b.title));
});

/** Every brand + collection + category for search / sitemaps. */
export const getAllStorefrontCollections = cache(async (): Promise<StorefrontCollection[]> => {
  const index = await getCatalogIndex();
  return Array.from(index.byHandle.values()).filter((c) => c.handle !== "all");
});

/** Metadata for a single collection/brand page (title, image, kind). */
export const getCollectionMeta = cache(
  async (handle: string): Promise<StorefrontCollection | null> => {
    const canonical = LEGACY_HANDLE_ALIASES[handle] || handle;
    const index = await getCatalogIndex();

    if (canonical === "all") return index.byHandle.get("all") ?? null;

    return index.byHandle.get(canonical) ?? null;
  },
);

/**
 * Categories nested under a collection root (admin Collection → Categories).
 * Empty when the handle is itself a leaf category or unknown.
 * All published children are returned — including zero-product categories —
 * so the storefront mirrors the admin tree.
 */
export const getCollectionCategories = cache(
  async (handle: string): Promise<StorefrontCollection[]> => {
    const canonical = LEGACY_HANDLE_ALIASES[handle] || handle;
    const index = await getCatalogIndex();
    const root = index.categoryRoots.find((c) => c.slug === canonical);
    if (!root?.children?.length) return [];

    return root.children
      .filter((c): c is ApiCategory & { slug: string } => Boolean(c.slug))
      .map((c) => {
        const meta = index.byHandle.get(c.slug);
        return (
          meta ?? {
            handle: c.slug,
            title: c.name,
            display_title: c.name,
            image: c.image ?? c.logo_url ?? null,
            kind: "category" as const,
            products_count: 0,
            description: c.description ?? null,
          }
        );
      });
  },
);

/** Parent collection for a category handle — used for breadcrumbs. */
export const getParentCollection = cache(
  async (handle: string): Promise<StorefrontCollection | null> => {
    const canonical = LEGACY_HANDLE_ALIASES[handle] || handle;
    const index = await getCatalogIndex();

    for (const root of index.categoryRoots) {
      if (!root.slug) continue;
      if (root.children?.some((c) => c.slug === canonical)) {
        return index.byHandle.get(root.slug) ?? null;
      }
    }

    return null;
  },
);

/**
 * Brands represented by products inside a collection (or category) handle.
 * `products_count` is scoped to this collection, not the full catalog.
 */
export const getCollectionBrands = cache(
  async (handle: string): Promise<StorefrontCollection[]> => {
    const [products, index] = await Promise.all([
      getCollectionProductList(handle),
      getCatalogIndex(),
    ]);

    const counts = new Map<string, number>();
    for (const product of products) {
      if (!product.brandSlug) continue;
      counts.set(product.brandSlug, (counts.get(product.brandSlug) || 0) + 1);
    }

    const brands: StorefrontCollection[] = [];
    for (const [slug, count] of counts) {
      const meta = index.byHandle.get(slug);
      if (meta?.kind === "brand") {
        brands.push({ ...meta, products_count: count });
      }
    }

    return brands.sort((a, b) => a.title.localeCompare(b.title));
  },
);

export type FeaturedStorefrontCategory = {
  title: string;
  handle: string;
  image: string;
  href: string;
  description: string | null;
  products_count: number;
  collection: {
    title: string;
    handle: string;
    href: string;
  } | null;
};

export type HomepageCategoryLogo = {
  title: string;
  handle: string;
  logo: string;
  href: string;
};

/**
 * Homepage category showcase: Featured + cover (“On homepage” KPI).
 * Pure picker lives in `homepageCategories.ts` so tests lock the contract.
 */
export const getFeaturedStorefrontCategories = cache(
  async (): Promise<FeaturedStorefrontCategory[]> => {
    // Shell is enough for covers/hrefs; product counts stay optional on Home.
    const index = await getCatalogShell();
    const collectionById = new Map(
      index.categoryRoots
        .filter((r): r is ApiCategory & { id: string; slug: string } =>
          Boolean(r.id && r.slug),
        )
        .map((r) => [r.id, r] as const),
    );

    return pickFeaturedStorefrontCategories(index.categoriesFlat, {
      byHandle: index.byHandle,
      collectionById,
    }).map((plate) => {
      if (!plate.collection) return plate;
      const parent = index.byHandle.get(plate.collection.handle);
      if (!parent) return plate;
      return {
        ...plate,
        collection: {
          ...plate.collection,
          href: hrefForStorefrontCollection(parent),
        },
      };
    });
  },
);

/**
 * Logo rail under the homepage hero — every category with `logo_url`.
 * Featured must never hide a logo (see `pickHomepageCategoryLogos` tests).
 */
export const getHomepageCategoryLogos = cache(
  async (): Promise<HomepageCategoryLogo[]> => {
    const index = await getCatalogShell();
    return pickHomepageCategoryLogos(index.categoriesFlat);
  },
);

/** Real product counts per collection handle (includes parent rollups). */
export const getCollectionCounts = cache(async (): Promise<Record<string, number>> => {
  const index = await getCatalogIndex();
  const counts: Record<string, number> = {};
  for (const [handle, col] of index.byHandle) {
    counts[handle] = col.products_count;
  }
  for (const [legacy, canonical] of Object.entries(LEGACY_HANDLE_ALIASES)) {
    counts[legacy] = counts[canonical] ?? 0;
  }
  return counts;
});

/** Category (+ collection + catalog) handles for /collections/[handle] static params. */
export const getCollectionHandles = cache(async (): Promise<string[]> => {
  const index = await getCatalogIndex();
  const handles = new Set<string>(["all"]);
  for (const [handle, col] of index.byHandle) {
    if (
      col.kind === "category" ||
      col.kind === "collection" ||
      col.kind === "catalog"
    ) {
      handles.add(handle);
    }
  }
  // Keep legacy aliases only when the live canonical handle still exists.
  for (const [legacy, canonical] of Object.entries(LEGACY_HANDLE_ALIASES)) {
    if (canonical === "all") {
      handles.add(legacy);
      continue;
    }
    if (index.byHandle.has(canonical)) {
      handles.add(legacy);
    }
  }
  return Array.from(handles);
});

/** Brand slugs for /brands/[slug] — every admin brand (empty pages show coming soon). */
export const getBrandHandles = cache(async (): Promise<string[]> => {
  const index = await getCatalogIndex();
  return Array.from(index.byHandle.values())
    .filter((c) => c.kind === "brand")
    .map((c) => c.handle);
});

/** Admin-controlled (Settings → Currency), cached once per request. */
export const getCurrency = cache(async (): Promise<Currency> => fetchCurrency());

/** Admin → Settings → General (store name / email / phone). */
export const getStoreSettings = cache(async (): Promise<StoreSettings> => fetchStoreSettings());

export const getSiteContent = cache(async (): Promise<SiteContent> => fetchSiteContent());

/** Admin → Settings → Shipping / Tax for checkout summary. */
export const getCheckoutSettings = cache(
  async (): Promise<CheckoutSettings> => fetchCheckoutSettings(),
);

export const getInventorySettings = cache(
  async (): Promise<InventorySettings> => fetchInventorySettings(),
);

export function formatPrice(price: string | number, currency: Currency = DEFAULT_CURRENCY) {
  const n = typeof price === "string" ? parseFloat(price) : price;
  const amount = n.toFixed(2);
  return currency.position === "after" ? `${amount} ${currency.symbol}` : `${currency.symbol} ${amount}`;
}

export type { Currency, StoreSettings, CheckoutSettings };

/** @deprecated Prefer getCollectionMeta — kept for gradual migration. */
export async function getCollection(handle: string) {
  return getCollectionMeta(handle);
}
