import { cookies } from "next/headers";
import { parseLocale, DEFAULT_LOCALE, LOCALE_COOKIE, type Locale } from "./index";

export type { Locale } from "./index";

/**
 * During `next build` prerender, `cookies()` opts the route into dynamic
 * rendering and aborts static generation (generateStaticParams / ISR).
 * Serve the default locale for the static shell; request-time rendering
 * still reads the shopper cookie below.
 */
function isBuildPrerender(): boolean {
  return process.env.NEXT_PHASE === "phase-production-build";
}

/** Server-only: reads locale cookie. Do not import from Client Components. */
export async function getServerLocale(): Promise<Locale> {
  if (isBuildPrerender()) return DEFAULT_LOCALE;

  try {
    const jar = await cookies();
    return parseLocale(jar.get(LOCALE_COOKIE)?.value);
  } catch {
    return DEFAULT_LOCALE;
  }
}
