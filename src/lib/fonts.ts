import {
  Cabin,
  Cairo,
  Cormorant_Garamond,
  Noto_Naskh_Arabic,
  Plus_Jakarta_Sans,
} from "next/font/google";

/**
 * English display — elegant serif matching Dokan Ward’s logo wordmark.
 * Weights trimmed to what the UI actually uses (no unused 300/400 downloads).
 */
export const fontDisplay = Cormorant_Garamond({
  subsets: ["latin", "latin-ext"],
  weight: ["400", "500", "600", "700"],
  variable: "--font-display",
  display: "swap",
  preload: true,
  adjustFontFallback: true,
});

/**
 * English body / UI — warm humanist sans; pairs cleanly with Cormorant.
 */
export const fontEnglish = Plus_Jakarta_Sans({
  subsets: ["latin", "latin-ext"],
  weight: ["400", "500", "600", "700"],
  variable: "--font-english",
  display: "swap",
  preload: true,
  adjustFontFallback: true,
});

/**
 * Arabic body / UI — Cairo: clear, warm, excellent for RTL storefronts.
 */
export const fontArabic = Cairo({
  subsets: ["arabic", "latin"],
  weight: ["400", "500", "600", "700"],
  variable: "--font-arabic",
  display: "swap",
  // Default storefront locale is EN — avoid competing with LCP font preload.
  preload: false,
  adjustFontFallback: true,
});

/**
 * Arabic display — refined Naskh for headings (pairs with Cormorant).
 * Not preloaded: headings paint after body; saves competing with LCP.
 */
export const fontArabicDisplay = Noto_Naskh_Arabic({
  subsets: ["arabic", "latin"],
  weight: ["500", "600", "700"],
  variable: "--font-arabic-display",
  display: "swap",
  preload: false,
  adjustFontFallback: true,
});

/** Kept as aliases so existing imports keep working. */
export const fontBody = fontEnglish;
export const fontHeading = fontDisplay;

/**
 * Accent labels — same face as English body (no duplicate download).
 * CSS still reads --font-anonymous via a :root alias to --font-english.
 */
export const fontAccent = fontEnglish;

/** Legal policies (/policies/*) — Cabin for English policy pages. */
export const fontPolicy = Cabin({
  subsets: ["latin", "latin-ext"],
  weight: ["400", "500", "600", "700"],
  variable: "--font-cabin",
  display: "swap",
  preload: false,
});
