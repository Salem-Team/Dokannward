/**
 * Offline chrome fallbacks when the admin API is unreachable.
 * Live values come from Admin → Settings → General and Website → Home.
 */
export const BRAND = {
  name: "Dokan Ward",
  url: "https://dokannward.com",
  currency: "EGP",
  logo: "/images/dokan-ward-logo.png",
  logoOnDark: "/images/dokan-ward-logo.png",
  announcement: "Bring nature indoors — shop home decor, plants & more",
  description:
    "Dokan Ward is Egypt’s home décor destination — premium artificial plants, vases, bakhoor, candles, lamps, and boho pieces curated since 2018.",
  ogImage: "/images/og-share.jpg",
  heroImage: "/images/hero-layers/hero-base.jpg",
  heroWordmark: "/images/dokan-ward-logo.png",
  heroAlt: "DOKAN WARD — Home Decor",
  social: {
    instagram: "https://www.instagram.com/dokan_ward_96/",
    tiktok: "",
    facebook: "",
  },
  nav: [
    { label: "Home", href: "/" },
    { label: "Shop", href: "/collections/all" },
    { label: "Collections", href: "/collections" },
    { label: "About", href: "/pages/about" },
    { label: "Contact", href: "/pages/contact" },
  ],
} as const;

/** Cache-buster for hero assets when paths are the built-in defaults. */
export const HERO_CACHE = "v5";

export function withHeroCache(src: string): string {
  if (!src) return src;
  if (/^https?:\/\//i.test(src)) return src;
  const base = src.split("?")[0] || src;
  if (
    base === BRAND.heroImage ||
    base === BRAND.heroWordmark ||
    base.startsWith("/images/hero-layers/") ||
    base === BRAND.logo
  ) {
    return `${base}?${HERO_CACHE}`;
  }
  return src;
}
