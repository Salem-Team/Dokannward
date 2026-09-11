/**
 * Site-wide image policy for next/image.
 * Qualities here MUST stay in sync with `images.qualities` in next.config.ts
 * — Next.js 15 returns HTTP 400 for any other `quality` value.
 */
export const IMAGE_QUALITY = {
  /** Product cards, thumbs, drawers, search */
  card: 75,
  /** Product detail hero — sharp enough, lighter than full editorial */
  pdp: 80,
  /** Brand tiles, category plates, home heroes, editorial frames */
  hero: 85,
} as const;

export type ImageQuality = (typeof IMAGE_QUALITY)[keyof typeof IMAGE_QUALITY];

const LOCAL_HOST_RE =
  /^(localhost|127\.0\.0\.1|0\.0\.0\.0|\[::1\])$/i;

function siteOrigin(): string {
  if (typeof window !== "undefined" && window.location?.origin) {
    return window.location.origin.replace(/\/$/, "");
  }
  const fromEnv = (
    process.env.NEXT_PUBLIC_SITE_URL ||
    process.env.NEXT_PUBLIC_STOREFRONT_URL ||
    ""
  ).trim();
  if (fromEnv) {
    try {
      return new URL(fromEnv).origin;
    } catch {
      /* fall through */
    }
  }
  const api = (process.env.NEXT_PUBLIC_API_URL || "").trim();
  if (api) {
    try {
      return new URL(api).origin;
    } catch {
      /* fall through */
    }
  }
  return "https://dokannward.com";
}

/** True when the storefront talks to a local Laravel API (dev / local serve). */
function isLocalApiHost(): boolean {
  const api = (process.env.NEXT_PUBLIC_API_URL || "").trim();
  if (!api) return process.env.NODE_ENV === "development";
  try {
    return LOCAL_HOST_RE.test(new URL(api).hostname);
  } catch {
    return process.env.NODE_ENV === "development";
  }
}

/**
 * Origin of the Laravel API (no trailing slash), e.g. http://localhost:8001.
 * APP_URL often omits the artisan port (`http://localhost`) which bricks
 * `/storage` images — always prefer the API origin in local mode.
 */
function localApiOrigin(): string | null {
  const api = (process.env.NEXT_PUBLIC_API_URL || "").trim();
  if (!api) return null;
  try {
    const url = new URL(api);
    if (!LOCAL_HOST_RE.test(url.hostname)) return null;
    return url.origin;
  } catch {
    return null;
  }
}

/**
 * Seeded category covers also ship under Next `public/images/categories/`.
 * Prefer that same-origin path when the storage URL is the dokannward pack —
 * works even if Laravel APP_URL / port is misconfigured.
 */
export function categoryPublicImageFallback(
  src?: string | null,
): string | null {
  if (!src) return null;
  try {
    const path = src.startsWith("/")
      ? src.split("?")[0] || ""
      : new URL(src).pathname;
    const match = path.match(
      /\/(?:storage\/)?categories\/(?:dokannward\/)?([^/]+?)\.(?:png|jpe?g|webp|avif|gif)$/i,
    );
    if (!match?.[1]) return null;
    // Prefer the compressed WebP pack under public/ (PNG kept as last resort).
    return `/images/categories/${match[1]}.webp`;
  } catch {
    return null;
  }
}

/**
 * Rewrite stale admin/dev absolute URLs (localhost:8000/storage/…) into a
 * browsable storefront path. Cart / wishlist localStorage often keeps those
 * forever after a product was first added on an old APP_URL.
 *
 * Local API mode: remap to the API origin (or relative `/storage`) so a bare
 * `APP_URL=http://localhost` (no :8001) still loads media.
 */
export function rewriteStorefrontMediaUrl(src: string): string {
  const value = src.trim();
  if (!value) return value;

  if (value.startsWith("/")) return value;

  try {
    const url = new URL(value);
    if (!LOCAL_HOST_RE.test(url.hostname)) return value;

    const isMediaPath =
      url.pathname.startsWith("/storage/") ||
      url.pathname.startsWith("/images/");

    // Local stack: Next + Laravel on loopback — pin media to the API port.
    if (isLocalApiHost()) {
      if (!isMediaPath) return value;

      // Category pack: serve from Next public (always available in this repo).
      const categoryLocal = categoryPublicImageFallback(
        `${url.pathname}${url.search}`,
      );
      if (categoryLocal && url.pathname.includes("/categories/")) {
        return categoryLocal;
      }

      const apiOrigin = localApiOrigin();
      if (apiOrigin) {
        return `${apiOrigin}${url.pathname}${url.search}`;
      }
      // Last resort: relative path (Next can rewrite /storage → Laravel).
      return `${url.pathname}${url.search}`;
    }

    // Keep the public path (+ query for cache-busters) on the live origin.
    if (isMediaPath) {
      // Category pack ships under Next public/ — prefer it over a wrong APP_URL.
      const categoryLocal = categoryPublicImageFallback(
        `${url.pathname}${url.search}`,
      );
      if (categoryLocal && url.pathname.includes("/categories/")) {
        return categoryLocal;
      }
      return `${siteOrigin()}${url.pathname}${url.search}`;
    }

    // Any other localhost media is unusable in production.
    return "";
  } catch {
    return value;
  }
}

/**
 * Normalize storefront media URLs.
 * - Drops ui-avatars placeholders (often 400 via the optimizer / DNS).
 * - Rewrites localhost Laravel storage URLs saved in old carts.
 * - Keeps absolute and relative storage / public image paths.
 */
export function storefrontImageSrc(
  src?: string | null,
): string | null {
  if (!src) return null;
  const rewritten = rewriteStorefrontMediaUrl(src);
  const value = rewritten.trim();
  if (!value) return null;
  if (value.includes("ui-avatars.com")) return null;
  return value;
}

/** First usable URL from a candidate list (after sanitization). */
export function pickStorefrontImage(
  ...candidates: Array<string | null | undefined>
): string | null {
  for (const candidate of candidates) {
    const src = storefrontImageSrc(candidate);
    if (src) return src;
  }
  return null;
}

/**
 * Ordered candidates for a cart/checkout/wishlist line:
 * color photo → product primary → gallery → chrome fallback.
 */
export function lineImageCandidates(input: {
  colorImage?: string | null;
  productImage?: string | null;
  productImages?: Array<string | null | undefined> | null;
  fallback?: string | null;
}): string[] {
  const seen = new Set<string>();
  const out: string[] = [];

  const push = (value?: string | null) => {
    const src = storefrontImageSrc(value);
    if (!src || seen.has(src)) return;
    seen.add(src);
    out.push(src);
  };

  push(input.colorImage);
  push(input.productImage);
  for (const image of input.productImages ?? []) push(image);
  push(input.fallback);

  return out;
}

export function resolveLineImage(input: {
  colorImage?: string | null;
  productImage?: string | null;
  productImages?: Array<string | null | undefined> | null;
  fallback?: string | null;
}): string | null {
  return lineImageCandidates(input)[0] ?? null;
}

/**
 * Admin uploads live under Laravel `/storage/…`.
 * Those must never go through `/_next/image`: Next caches optimizer 404s as
 * `immutable` for a year, so a race right after upload bricks the plate in Safari.
 */
export function isLaravelStorageSrc(src?: string | null): boolean {
  if (!src) return false;
  try {
    if (src.startsWith("/storage/")) return true;
    const url = new URL(src, "https://dokannward.com");
    return url.pathname.startsWith("/storage/");
  } catch {
    return src.includes("/storage/");
  }
}

/** SVGs + Laravel storage: render as plain <img>, skip the optimizer. */
export function shouldUnoptimizeStorefrontImage(src?: string | null): boolean {
  if (!src) return false;
  if (isLaravelStorageSrc(src)) return true;
  const path = src.split("?")[0]?.toLowerCase() ?? "";
  return path.endsWith(".svg");
}
