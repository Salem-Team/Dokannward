/**
 * Central ROOTK / white-label branding resolver for the Next.js storefront (server).
 *
 * Priority (later wins within each layer; layers merged low → high):
 * 1. Neutral local defaults (no real product name)
 * 2. admin/branding/*.json + .rootk/branding.json (filesystem)
 * 3. NEXT_PUBLIC_ROOTK_TENANT_* / ROOTK_TENANT_* env
 */

import { readFileSync, existsSync } from "node:fs";
import { join } from "node:path";

export interface TenantBranding {
  displayName: string;
  displayNameAr: string;
  shortName: string;
  logoLightUrl: string;
  logoDarkUrl: string;
  faviconUrl: string;
  colors: Record<string, string>;
  websiteTitle: string;
  websiteTitleAr: string;
  websiteDescription: string;
  websiteDescriptionAr: string;
  exportPrefix: string;
  mobileAppName: string;
  domain: string | null;
}

const NEUTRAL: TenantBranding = {
  displayName: "Default Company",
  displayNameAr: "الشركة الافتراضية",
  shortName: "Store",
  logoLightUrl: "/images/brand-logo.png",
  logoDarkUrl: "/images/brand-logo-on-dark.png",
  faviconUrl: "/favicon.ico",
  colors: {
    primary_color: "#0a0a0a",
    secondary_color: "#fafafa",
    accent_color: "#2a2a2a",
    primary_hover: "#000000",
    theme_color_light: "#ffffff",
    theme_color_dark: "#0a0a0a",
    mobile_splash_color: "#0a0a0a",
  },
  websiteTitle: "Default Company — Luxury Fashion Store",
  websiteTitleAr: "الشركة الافتراضية — متجر أزياء فاخرة",
  websiteDescription:
    "Curated luxury bags, shoes, and accessories with secure checkout and nationwide delivery.",
  websiteDescriptionAr:
    "شنط وإكسسوارات وأحذية فاخرة مختارة مع توصيل داخل مصر وتجربة شراء واضحة.",
  exportPrefix: "Store",
  mobileAppName: "Store",
  domain: null,
};

function envStr(...keys: string[]): string | undefined {
  for (const key of keys) {
    const v = process.env[key];
    if (typeof v === "string" && v.trim() !== "") return v.trim();
  }
  return undefined;
}

function readJson(path: string): Record<string, unknown> | null {
  try {
    if (!existsSync(path)) return null;
    const raw = readFileSync(path, "utf8");
    const parsed = JSON.parse(raw) as unknown;
    return parsed && typeof parsed === "object"
      ? (parsed as Record<string, unknown>)
      : null;
  } catch {
    return null;
  }
}

function rootkPaths(relative: string): string[] {
  const cwd = process.cwd();
  return [
    join(cwd, relative),
    join(cwd, "admin", relative),
    join(cwd, "..", relative),
  ];
}

function firstJson(relative: string): Record<string, unknown> | null {
  for (const path of rootkPaths(relative)) {
    const data = readJson(path);
    if (data) return data;
  }
  return null;
}

function parseBrandColors(raw: string | undefined): Record<string, string> {
  if (!raw) return {};
  try {
    const parsed = JSON.parse(raw) as unknown;
    if (!Array.isArray(parsed)) return {};
    const out: Record<string, string> = {};
    const named = ["primary_color", "secondary_color", "accent_color"];
    parsed.forEach((item, index) => {
      if (typeof item === "string" && item.startsWith("#") && named[index]) {
        out[named[index]] = item;
        return;
      }
      if (
        item &&
        typeof item === "object" &&
        typeof (item as { name?: string }).name === "string" &&
        typeof (item as { value?: string }).value === "string"
      ) {
        out[(item as { name: string }).name] = (item as { value: string }).value;
      }
    });
    return out;
  } catch {
    return {};
  }
}

function mapRootkBrandingJson(
  json: Record<string, unknown>,
): Partial<TenantBranding> {
  const company = (json.company as Record<string, string>) || {};
  const logos = (json.logos as Record<string, string>) || {};
  const colors = (json.colors as Record<string, string>) || {};
  const seo = (json.seo as Record<string, string>) || {};
  const pdf = (json.pdf as Record<string, string>) || {};
  const mobile = (json.mobile as Record<string, string>) || {};
  const variables = Array.isArray(json.variables)
    ? (json.variables as Array<{ name?: string; value?: string }>)
    : [];

  const colorMap: Record<string, string> = { ...NEUTRAL.colors };
  if (colors.primary) colorMap.primary_color = colors.primary;
  if (colors.secondary) colorMap.secondary_color = colors.secondary;
  if (colors.accent) colorMap.accent_color = colors.accent;
  for (const v of variables) {
    if (v?.name && v?.value) colorMap[v.name] = v.value;
  }

  return {
    displayName: company.name || undefined,
    displayNameAr: company.nameAr || undefined,
    shortName: company.shortName || company.name || undefined,
    logoLightUrl: logos.light || undefined,
    logoDarkUrl: logos.dark || logos.light || undefined,
    faviconUrl: logos.favicon || undefined,
    colors: colorMap,
    websiteTitle: seo.title || company.name || undefined,
    websiteDescription: seo.description || undefined,
    websiteTitleAr: seo.titleAr || undefined,
    websiteDescriptionAr: seo.descriptionAr || undefined,
    exportPrefix: pdf.exportPrefix || company.shortName || company.name || undefined,
    mobileAppName: mobile.appName || company.name || undefined,
    domain: typeof json.domain === "string" ? json.domain : null,
  };
}

function mapTenantJson(
  json: Record<string, unknown>,
): Partial<TenantBranding> {
  return {
    displayName:
      (json.displayName as string) || (json.name as string) || undefined,
    displayNameAr: (json.displayNameAr as string) || undefined,
    shortName: (json.slug as string) || undefined,
    logoLightUrl: (json.logoUrl as string) || undefined,
    logoDarkUrl: (json.logoUrl as string) || undefined,
    colors: {
      ...NEUTRAL.colors,
      ...(json.primaryColor
        ? { primary_color: String(json.primaryColor) }
        : {}),
      ...(json.secondaryColor
        ? { secondary_color: String(json.secondaryColor) }
        : {}),
    },
    domain: typeof json.domain === "string" ? json.domain : null,
  };
}

function mapEnv(): Partial<TenantBranding> {
  const brandColors = parseBrandColors(
    envStr("NEXT_PUBLIC_ROOTK_TENANT_BRAND_COLORS", "ROOTK_TENANT_BRAND_COLORS"),
  );
  const primary = envStr(
    "NEXT_PUBLIC_ROOTK_TENANT_PRIMARY_COLOR",
    "ROOTK_TENANT_PRIMARY_COLOR",
  );
  const secondary = envStr(
    "NEXT_PUBLIC_ROOTK_TENANT_SECONDARY_COLOR",
    "ROOTK_TENANT_SECONDARY_COLOR",
  );

  return {
    displayName: envStr(
      "NEXT_PUBLIC_ROOTK_TENANT_DISPLAY_NAME",
      "ROOTK_TENANT_DISPLAY_NAME",
    ),
    displayNameAr: envStr(
      "NEXT_PUBLIC_ROOTK_TENANT_DISPLAY_NAME_AR",
      "ROOTK_TENANT_DISPLAY_NAME_AR",
    ),
    logoLightUrl: envStr(
      "NEXT_PUBLIC_ROOTK_TENANT_LOGO_URL",
      "ROOTK_TENANT_LOGO_URL",
    ),
    logoDarkUrl: envStr(
      "NEXT_PUBLIC_ROOTK_TENANT_LOGO_URL",
      "ROOTK_TENANT_LOGO_URL",
    ),
    colors: {
      ...NEUTRAL.colors,
      ...(primary ? { primary_color: primary } : {}),
      ...(secondary ? { secondary_color: secondary } : {}),
      ...brandColors,
    },
    domain: envStr("NEXT_PUBLIC_ROOTK_TENANT_DOMAIN", "ROOTK_TENANT_DOMAIN") || null,
    websiteTitle: envStr(
      "NEXT_PUBLIC_ROOTK_TENANT_WEBSITE_TITLE",
      "ROOTK_TENANT_WEBSITE_TITLE",
      "BRAND_WEBSITE_TITLE",
    ),
    websiteDescription: envStr(
      "NEXT_PUBLIC_ROOTK_TENANT_WEBSITE_DESCRIPTION",
      "ROOTK_TENANT_WEBSITE_DESCRIPTION",
      "BRAND_WEBSITE_DESCRIPTION",
    ),
    mobileAppName: envStr(
      "NEXT_PUBLIC_ROOTK_TENANT_DISPLAY_NAME",
      "ROOTK_TENANT_DISPLAY_NAME",
    ),
    exportPrefix: envStr(
      "NEXT_PUBLIC_ROOTK_TENANT_DISPLAY_NAME",
      "ROOTK_TENANT_DISPLAY_NAME",
    ),
  };
}

function merge(
  base: TenantBranding,
  patch: Partial<TenantBranding>,
): TenantBranding {
  const colors = { ...base.colors, ...(patch.colors || {}) };
  return {
    displayName: patch.displayName?.trim() || base.displayName,
    displayNameAr: patch.displayNameAr?.trim() || base.displayNameAr,
    shortName: patch.shortName?.trim() || base.shortName,
    logoLightUrl: patch.logoLightUrl?.trim() || base.logoLightUrl,
    logoDarkUrl: patch.logoDarkUrl?.trim() || base.logoDarkUrl,
    faviconUrl: patch.faviconUrl?.trim() || base.faviconUrl,
    colors,
    websiteTitle: patch.websiteTitle?.trim() || base.websiteTitle,
    websiteTitleAr: patch.websiteTitleAr?.trim() || base.websiteTitleAr,
    websiteDescription:
      patch.websiteDescription?.trim() || base.websiteDescription,
    websiteDescriptionAr:
      patch.websiteDescriptionAr?.trim() || base.websiteDescriptionAr,
    exportPrefix: patch.exportPrefix?.trim() || base.exportPrefix,
    mobileAppName: patch.mobileAppName?.trim() || base.mobileAppName,
    domain: patch.domain !== undefined ? patch.domain : base.domain,
  };
}

let cached: TenantBranding | null = null;

/** Resolve tenant branding (cached per process). */
export function getTenantBranding(): TenantBranding {
  if (cached) return cached;

  let resolved = { ...NEUTRAL, colors: { ...NEUTRAL.colors } };

  const localBrand = firstJson("branding/brand.json");
  const localSeo = firstJson("branding/seo.json");
  const localColors = firstJson("branding/colors.json");
  const localAssets = firstJson("branding/assets.json");

  if (localBrand || localSeo || localColors || localAssets) {
    resolved = merge(resolved, {
      displayName: (localBrand?.company_name as string) || undefined,
      displayNameAr: (localBrand?.company_name_ar as string) || undefined,
      shortName: (localBrand?.company_short_name as string) || undefined,
      websiteTitle: (localSeo?.website_title as string) || undefined,
      websiteTitleAr: (localSeo?.website_title_ar as string) || undefined,
      websiteDescription: (localSeo?.website_description as string) || undefined,
      websiteDescriptionAr:
        (localSeo?.website_description_ar as string) || undefined,
      logoLightUrl: (localAssets?.logo_light as string) || undefined,
      logoDarkUrl: (localAssets?.logo_dark as string) || undefined,
      faviconUrl: (localAssets?.favicon as string) || undefined,
      colors: {
        ...resolved.colors,
        ...((localColors as Record<string, string>) || {}),
      },
      exportPrefix:
        (localBrand?.company_short_name as string) ||
        (localBrand?.company_name as string) ||
        undefined,
      mobileAppName: (localBrand?.company_name as string) || undefined,
    });
  }

  const rootkBranding = firstJson(".rootk/branding.json");
  if (rootkBranding) {
    resolved = merge(resolved, mapRootkBrandingJson(rootkBranding));
  }

  const rootkTenant = firstJson(".rootk/tenant.json");
  if (rootkTenant) {
    resolved = merge(resolved, mapTenantJson(rootkTenant));
  }

  resolved = merge(resolved, mapEnv());

  cached = resolved;
  return resolved;
}

/** Reset cache (tests / after ROOTK sync). */
export function clearTenantBrandingCache(): void {
  cached = null;
}

/** CSS custom properties for :root anti-FOUC / runtime theme. */
export function brandingCssVariables(b: TenantBranding = getTenantBranding()): string {
  const c = b.colors;
  const pairs: Array<[string, string | undefined]> = [
    ["--brand-primary", c.primary_color],
    ["--brand-secondary", c.secondary_color],
    ["--brand-accent", c.accent_color],
    ["--brand-primary-hover", c.primary_hover],
    ["--theme-color-meta", c.theme_color_light],
    ["--color-ink", c.primary_color],
    ["--color-black", c.primary_hover || c.primary_color],
  ];
  return pairs
    .filter(([, v]) => typeof v === "string" && v !== "")
    .map(([k, v]) => `${k}: ${v};`)
    .join(" ");
}
