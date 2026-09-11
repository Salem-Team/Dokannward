"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { useRouter } from "next/navigation";
import {
  isLocale,
  localizeLabel,
  localeDocumentAttrs,
  t as translate,
  type Locale,
} from "@/lib/i18n";
import { DEFAULT_LOCALE, LOCALE_COOKIE } from "@/lib/i18n/types";

type LocaleContextValue = {
  locale: Locale;
  setLocale: (next: Locale) => void;
  t: (key: string, vars?: Record<string, string | number>) => string;
  localizeLabel: (label: string, href?: string) => string;
  isRtl: boolean;
};

const LocaleContext = createContext<LocaleContextValue | null>(null);

/** Prevents hydrate → refresh → remount loops (Strict Mode / HMR / runtime errors). */
const SYNC_FLAG = "dw_locale_synced";

function readCookieLocale(): Locale | null {
  if (typeof document === "undefined") return null;
  try {
    const match = document.cookie.match(
      new RegExp(`(?:^|; )${LOCALE_COOKIE}=([^;]*)`),
    );
    const value = match?.[1] ? decodeURIComponent(match[1].trim()) : null;
    return isLocale(value) ? value : null;
  } catch {
    return null;
  }
}

function readStoredLocale(): Locale | null {
  try {
    const stored = localStorage.getItem(LOCALE_COOKIE);
    return isLocale(stored) ? stored : null;
  } catch {
    return null;
  }
}

function writeLocaleCookie(locale: Locale) {
  const maxAge = 60 * 60 * 24 * 365;
  document.cookie = `${LOCALE_COOKIE}=${encodeURIComponent(locale)};path=/;max-age=${maxAge};samesite=lax`;
  try {
    localStorage.setItem(LOCALE_COOKIE, locale);
  } catch {
    /* ignore */
  }
}

function applyDocumentLocale(locale: Locale) {
  const { lang, dir } = localeDocumentAttrs(locale);
  const root = document.documentElement;
  if (root.lang !== lang) root.lang = lang;
  if (root.dir !== dir) root.dir = dir;
  root.classList.toggle("locale-ar", locale === "ar");
  root.classList.toggle("locale-en", locale === "en");
}

export function LocaleProvider({
  children,
  initialLocale,
}: {
  children: ReactNode;
  initialLocale: Locale;
}) {
  const router = useRouter();
  const safeInitial = isLocale(initialLocale) ? initialLocale : DEFAULT_LOCALE;
  const [locale, setLocaleState] = useState<Locale>(safeInitial);

  useEffect(() => {
    applyDocumentLocale(locale);
  }, [locale]);

  /**
   * One-shot client hydrate. Do not also sync `initialLocale` → state in a
   * second effect — that en↔ar fight + router.refresh() reloaded the page
   * every few hundred ms when the cookie lagged behind localStorage.
   */
  useEffect(() => {
    const preferred = readCookieLocale() ?? readStoredLocale();

    if (!preferred) {
      writeLocaleCookie(safeInitial);
      return;
    }

    // Keep cookie + localStorage mirrored even when they already match SSR.
    writeLocaleCookie(preferred);

    if (preferred === safeInitial) return;

    setLocaleState(preferred);

    // Soft-refresh RSC at most once per tab+locale. Skips Strict Mode doubles
    // and runtime-error remount storms.
    try {
      if (sessionStorage.getItem(SYNC_FLAG) === preferred) return;
      sessionStorage.setItem(SYNC_FLAG, preferred);
    } catch {
      // No sessionStorage → skip refresh; client chrome still switches language.
      return;
    }
    router.refresh();
    // Mount-only on purpose — re-running on prop change restarts the loop.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const setLocale = useCallback(
    (next: Locale) => {
      const safe = isLocale(next) ? next : DEFAULT_LOCALE;
      // Segmented control may re-click the active locale — don't refresh.
      if (safe === locale) {
        writeLocaleCookie(safe);
        return;
      }
      setLocaleState(safe);
      writeLocaleCookie(safe);
      applyDocumentLocale(safe);
      try {
        sessionStorage.setItem(SYNC_FLAG, safe);
      } catch {
        /* ignore */
      }
      router.refresh();
    },
    [locale, router],
  );

  const value = useMemo<LocaleContextValue>(
    () => ({
      locale,
      setLocale,
      t: (key, vars) => translate(locale, key, vars),
      localizeLabel: (label, href) => localizeLabel(locale, label, href),
      isRtl: locale === "ar",
    }),
    [locale, setLocale],
  );

  return (
    <LocaleContext.Provider value={value}>{children}</LocaleContext.Provider>
  );
}

export function useLocale() {
  const ctx = useContext(LocaleContext);
  if (!ctx) {
    throw new Error("useLocale must be used within LocaleProvider");
  }
  return ctx;
}
