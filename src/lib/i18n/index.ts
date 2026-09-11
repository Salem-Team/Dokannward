import { messages, LABEL_KEYS, HREF_LABEL_KEYS } from "./messages";
import {
  DEFAULT_LOCALE,
  LOCALE_COOKIE,
  type Locale,
} from "./types";

export type { Locale } from "./types";
export { DEFAULT_LOCALE, LOCALE_COOKIE, LOCALES } from "./types";

export function isLocale(value: unknown): value is Locale {
  return value === "en" || value === "ar";
}

export function parseLocale(value: string | null | undefined): Locale {
  return value === "ar" ? "ar" : DEFAULT_LOCALE;
}

export function t(
  locale: Locale,
  key: string,
  vars?: Record<string, string | number>,
): string {
  // Guard invalid/undefined locale (HMR, bad cookie, missing prop) so
  // `messages[locale][key]` never throws — e.g. reading 'a11y.skip'.
  const dict = messages[isLocale(locale) ? locale : DEFAULT_LOCALE] ?? messages.en;
  const raw = dict[key] ?? messages.en[key] ?? key;
  if (!vars) return raw;
  return Object.entries(vars).reduce(
    (s, [k, v]) => s.replaceAll(`{${k}}`, String(v)),
    raw,
  );
}

/** Translate a CMS / nav label using English label map, then href map. */
export function localizeLabel(
  locale: Locale,
  label: string,
  href?: string,
): string {
  if (locale === "en") return label;
  const key = LABEL_KEYS[label];
  if (key) return t(locale, key);
  if (href && HREF_LABEL_KEYS[href]) {
    return t(locale, HREF_LABEL_KEYS[href]);
  }
  return label;
}

/** Prefer Arabic override when English CMS text matches a known default. */
export function localizeCmsText(
  locale: Locale,
  english: string | undefined | null,
  key: string,
): string {
  const fallback = english?.trim() || "";
  if (locale === "en") return fallback || t("en", key);
  const mapped = LABEL_KEYS[fallback];
  if (mapped) return t(locale, mapped);
  // If CMS still has the English default that matches our key's EN string, swap
  if (fallback && fallback === messages.en[key]) return t(locale, key);
  if (!fallback) return t(locale, key);
  return fallback;
}

/** Map FAQ tags (and common aliases) → message keys for q / a / tag. */
const FAQ_TAG_KEYS: Record<
  string,
  { q: string; a: string; tag: string }
> = {
  returns: {
    q: "faq.returns.q",
    a: "faq.returns.a",
    tag: "faq.tag.returns",
  },
  delivery: {
    q: "faq.delivery.q",
    a: "faq.delivery.a",
    tag: "faq.tag.delivery",
  },
  shipping: {
    q: "faq.delivery.q",
    a: "faq.delivery.a",
    tag: "faq.tag.delivery",
  },
  origin: {
    q: "faq.track.q",
    a: "faq.track.a",
    tag: "faq.tag.origin",
  },
  tracking: {
    q: "faq.tracking.q",
    a: "faq.tracking.a",
    tag: "faq.tag.tracking",
  },
};

/**
 * Localize a FAQ row. Prefer tag→keys (stable), then LABEL_KEYS string match.
 * Avoids silent English when CMS punctuation/apostrophes drift from defaults.
 */
export function localizeFaqItem(
  locale: Locale,
  item: { q: string; a: string; tag?: string },
): { q: string; a: string; tag?: string } {
  if (locale === "en") {
    return { q: item.q, a: item.a, tag: item.tag };
  }

  const tagNorm = (item.tag || "")
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "");
  const byTag = tagNorm ? FAQ_TAG_KEYS[tagNorm] : undefined;

  if (byTag) {
    return {
      q: t(locale, byTag.q),
      a: t(locale, byTag.a),
      tag: t(locale, byTag.tag),
    };
  }

  // Fall back: match known English question → keys, answer via LABEL_KEYS.
  const qKey = LABEL_KEYS[item.q.trim()];
  if (qKey?.startsWith("faq.") && qKey.endsWith(".q")) {
    const base = qKey.slice(0, -2); // "faq.returns."
    return {
      q: t(locale, qKey),
      a: t(locale, `${base}a`),
      tag: item.tag
        ? localizeLabel(locale, item.tag)
        : undefined,
    };
  }

  return {
    q: localizeLabel(locale, item.q),
    a: localizeLabel(locale, item.a),
    tag: item.tag ? localizeLabel(locale, item.tag) : undefined,
  };
}

export function localeDocumentAttrs(locale: Locale): {
  lang: string;
  dir: "rtl" | "ltr";
} {
  return locale === "ar"
    ? { lang: "ar", dir: "rtl" }
    : { lang: "en-EG", dir: "ltr" };
}
