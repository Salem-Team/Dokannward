/** Shared hero asset URLs (safe for server + client). */
export { BRAND, HERO_CACHE, withHeroCache } from "@/lib/brand";
import { BRAND, withHeroCache } from "@/lib/brand";

/** Studio photo path — fed through next/image for AVIF/WebP LCP. */
export const HERO_BASE_PATH = BRAND.heroImage;

/** Cache-busted URL for non-optimizer consumers. */
export const HERO_BASE_SRC = withHeroCache(HERO_BASE_PATH);

/** Circular brand emblem — crisp on the hero plate. */
export const HERO_LOGO_SRC = withHeroCache(BRAND.heroWordmark);
