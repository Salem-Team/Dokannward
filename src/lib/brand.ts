/**
 * Client-safe brand chrome defaults.
 * Prefer NEXT_PUBLIC_ROOTK_TENANT_* env (injected by ROOTK). File-based
 * `.rootk/branding.json` is resolved on the server via getTenantBranding().
 *
 * Code fallbacks are intentionally NEUTRAL (no real product name) so the
 * template stays white-label. Packaged tenant values live in admin/branding/*
 * and ROOTK overlays — never hardcode a customer brand here.
 */

function envStr(...keys: string[]): string | undefined {
  for (const key of keys) {
    const v = process.env[key];
    if (typeof v === "string" && v.trim() !== "") return v.trim();
  }
  return undefined;
}

const displayName =
  envStr(
    "NEXT_PUBLIC_ROOTK_TENANT_DISPLAY_NAME",
    "ROOTK_TENANT_DISPLAY_NAME",
  ) || "Default Company";

const displayNameAr =
  envStr(
    "NEXT_PUBLIC_ROOTK_TENANT_DISPLAY_NAME_AR",
    "ROOTK_TENANT_DISPLAY_NAME_AR",
  ) || "الشركة الافتراضية";

const logoLight =
  envStr("NEXT_PUBLIC_ROOTK_TENANT_LOGO_URL", "ROOTK_TENANT_LOGO_URL") ||
  "/images/brand-logo.png";

const siteUrl =
  process.env.NEXT_PUBLIC_SITE_URL?.replace(/\/$/, "") ||
  (envStr("NEXT_PUBLIC_ROOTK_TENANT_DOMAIN", "ROOTK_TENANT_DOMAIN")
    ? `https://${envStr("NEXT_PUBLIC_ROOTK_TENANT_DOMAIN", "ROOTK_TENANT_DOMAIN")}`
    : "http://localhost:3000");

const bilingualTitle =
  displayNameAr && displayNameAr !== displayName
    ? `${displayName} | ${displayNameAr}`
    : displayName;

export const BRAND = {
  name: displayName,
  nameAr: displayNameAr,
  url: siteUrl,
  currency: "EGP",
  logo: logoLight,
  logoOnDark:
    envStr("NEXT_PUBLIC_ROOTK_TENANT_LOGO_URL", "ROOTK_TENANT_LOGO_URL") ||
    "/images/brand-logo-on-dark.png",
  announcement: "Shop our latest arrivals!",
  title: `${bilingualTitle} — Home Decor`,
  titleAr: `${displayNameAr} | ${displayName} — ديكور منزلي`,
  description: `${displayName}${displayNameAr ? ` (${displayNameAr})` : ""} — curated home décor with secure checkout and nationwide delivery.`,
  descriptionAr: `${displayNameAr} (${displayName}) — ديكور منزلي مختار مع توصيل داخل مصر.`,
  ogImage: "/images/og-share.jpg",
  heroImage: "/images/hero-layers/hero-base.jpg",
  heroWordmark: "/images/brand-logo.png",
  heroAlt: `${displayName.toUpperCase()} — Home Decor`,
  social: {
    instagram: "",
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
export const HERO_CACHE = "v7";

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
