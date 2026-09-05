import type { ApiCategory } from "@/lib/api";
import type {
  FeaturedStorefrontCategory,
  HomepageCategoryLogo,
  StorefrontCollection,
} from "@/lib/catalog";
import {
  categoryPublicImageFallback,
  storefrontImageSrc,
} from "@/lib/media";

/**
 * Synthetic collection roots are injected by the categories API as tree
 * wrappers (`id === collection_id`). They are not real Category rows and must
 * never appear as logo marks or Shop-by-category plates.
 */
export function isSyntheticCollectionRoot(
  c: Pick<ApiCategory, "id" | "collection_id">,
): boolean {
  return (
    c.collection_id != null &&
    c.id != null &&
    String(c.collection_id) === String(c.id)
  );
}

/**
 * /collections filter chips — every real admin category (not synthetic
 * collection wrappers). Featured sorts first, then position.
 */
export function pickStorefrontCategoryChips(
  categoriesFlat: ApiCategory[],
  counts: Map<string, number> | Record<string, number>,
): Array<{
  title: string;
  handle: string;
  href: string;
  products_count: number;
}> {
  const getCount = (slug: string) => {
    if (counts instanceof Map) return counts.get(slug) ?? 0;
    return counts[slug] ?? 0;
  };

  const seen = new Set<string>();
  const picked: ApiCategory[] = [];

  for (const c of categoriesFlat) {
    if (!c.slug) continue;
    if (isSyntheticCollectionRoot(c)) continue;
    if (seen.has(c.slug)) continue;
    seen.add(c.slug);
    picked.push(c);
  }

  return picked
    .sort((a, b) => {
      const feat =
        Number(b.is_featured === true) - Number(a.is_featured === true);
      if (feat !== 0) return feat;
      const pos = (a.position ?? 0) - (b.position ?? 0);
      if (pos !== 0) return pos;
      return a.name.localeCompare(b.name);
    })
    .map((c) => {
      const handle = c.slug as string;
      return {
        title: c.name,
        handle,
        href: `/collections/${handle}`,
        products_count: getCount(handle),
      };
    });
}

/**
 * Logo rail under the hero.
 *
 * Rule (locked by tests): every real category with a non-empty `logo_url`
 * appears — Featured must NOT gate visibility. Featured only sorts first.
 * This mirrors the admin “With logo” KPI so Homewear-style rows never vanish
 * after someone toggles Featured off.
 */
export function pickHomepageCategoryLogos(
  categoriesFlat: ApiCategory[],
  limit = 24,
): HomepageCategoryLogo[] {
  const seen = new Set<string>();
  const picked: ApiCategory[] = [];

  for (const c of categoriesFlat) {
    if (!c.slug || !c.logo_url?.trim()) continue;
    if (isSyntheticCollectionRoot(c)) continue;
    if (seen.has(c.slug)) continue;
    seen.add(c.slug);
    picked.push(c);
  }

  return picked
    .sort((a, b) => {
      const feat =
        Number(b.is_featured === true) - Number(a.is_featured === true);
      if (feat !== 0) return feat;
      return (a.position ?? 0) - (b.position ?? 0);
    })
    .slice(0, limit)
    .map((c) => {
      const raw = (c.logo_url as string).trim();
      return {
        title: c.name,
        handle: c.slug as string,
        logo:
          storefrontImageSrc(raw) ||
          categoryPublicImageFallback(raw) ||
          raw,
        href: `/collections/${c.slug}`,
      };
    });
}

type ShowcaseIndex = {
  byHandle: Map<string, StorefrontCollection>;
  collectionById: Map<string, ApiCategory>;
};

/**
 * Shop by category banner stack — Featured + cover only (“On homepage” KPI).
 */
export function pickFeaturedStorefrontCategories(
  categoriesFlat: ApiCategory[],
  index: ShowcaseIndex,
  limit = 12,
): FeaturedStorefrontCategory[] {
  const seen = new Set<string>();
  const picked: ApiCategory[] = [];

  for (const c of categoriesFlat) {
    if (c.is_featured !== true) continue;
    if (!c.slug) continue;
    if (isSyntheticCollectionRoot(c)) continue;
    if (seen.has(c.slug)) continue;
    seen.add(c.slug);
    picked.push(c);
  }

  return picked
    .sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
    .slice(0, limit)
    .map((c) => {
      const handle = c.slug as string;
      const meta = index.byHandle.get(handle);
      const parentRoot = c.collection_id
        ? index.collectionById.get(c.collection_id)
        : null;
      const parentMeta = parentRoot?.slug
        ? index.byHandle.get(parentRoot.slug)
        : null;
      const collection =
        parentMeta?.kind === "collection"
          ? {
              title: parentMeta.display_title || parentMeta.title,
              handle: parentMeta.handle,
              href: `/collections/${parentMeta.handle}`,
            }
          : null;
      const rawCover =
        c.image?.trim() ||
        c.logo_url?.trim() ||
        parentRoot?.image?.trim() ||
        parentMeta?.image?.trim() ||
        "";
      const cover =
        storefrontImageSrc(rawCover) ||
        categoryPublicImageFallback(rawCover) ||
        rawCover;

      return {
        title: c.name,
        handle,
        image: cover,
        href: `/collections/${handle}`,
        description: c.description?.trim() || meta?.description || null,
        products_count: meta?.products_count ?? 0,
        collection,
      };
    });
}
