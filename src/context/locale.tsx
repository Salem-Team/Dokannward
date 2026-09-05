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
  localizeLabel,
  localeDocumentAttrs,
  t as translate,
  type Locale,
} from "@/lib/i18n";
import { LOCALE_COOKIE } from "@/lib/i18n/types";

type LocaleContextValue = {
  locale: Locale;
  setLocale: (next: Locale) => void;
  t: (key: string, vars?: Record<string, string | number>) => string;
  localizeLabel: (label: string, href?: string) => string;
  isRtl: boolean;
};

const LocaleContext = createContext<LocaleContextValue | null>(null);

function writeLocaleCookie(locale: Locale) {
  const maxAge = 60 * 60 * 24 * 365;
  document.cookie = `${LOCALE_COOKIE}=${locale};path=/;max-age=${maxAge};samesite=lax`;
  try {
    localStorage.setItem(LOCALE_COOKIE, locale);
  } catch {
    /* ignore */
  }
}

function applyDocumentLocale(locale: Locale) {
  const { lang, dir } = localeDocumentAttrs(locale);
  document.documentElement.lang = lang;
  document.documentElement.dir = dir;
  document.documentElement.classList.toggle("locale-ar", locale === "ar");
  document.documentElement.classList.toggle("locale-en", locale === "en");
}

export function LocaleProvider({
  children,
  initialLocale,
}: {
  children: ReactNode;
  initialLocale: Locale;
}) {
  const router = useRouter();
  const [locale, setLocaleState] = useState<Locale>(initialLocale);

  // Keep client locale in sync when the server re-renders after cookie change.
  useEffect(() => {
    setLocaleState(initialLocale);
    applyDocumentLocale(initialLocale);
  }, [initialLocale]);

  useEffect(() => {
    applyDocumentLocale(locale);
  }, [locale]);

  // Prefer cookie; fall back to localStorage if cookie missing (first visit).
  useEffect(() => {
    try {
      const cookieMatch = document.cookie.match(
        new RegExp(`(?:^|; )${LOCALE_COOKIE}=([^;]*)`),
      );
      const fromCookie = cookieMatch?.[1];
      if (fromCookie === "ar" || fromCookie === "en") {
        if (fromCookie !== initialLocale) {
          setLocaleState(fromCookie);
          applyDocumentLocale(fromCookie);
        }
        return;
      }
      const stored = localStorage.getItem(LOCALE_COOKIE);
      if (stored === "ar" || stored === "en") {
        if (stored !== initialLocale) {
          setLocaleState(stored);
          writeLocaleCookie(stored);
          applyDocumentLocale(stored);
          router.refresh();
        }
      }
    } catch {
      /* ignore */
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- hydrate once
  }, []);

  const setLocale = useCallback(
    (next: Locale) => {
      setLocaleState(next);
      writeLocaleCookie(next);
      applyDocumentLocale(next);
      router.refresh();
    },
    [router],
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
