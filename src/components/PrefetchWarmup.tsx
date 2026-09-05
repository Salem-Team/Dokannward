"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";

/**
 * Warms the Next.js client router cache for key catalog routes so product /
 * brand navigations feel instant — but only after the page is idle so it
 * never competes with LCP on cellular.
 */
export function PrefetchWarmup({ hrefs }: { hrefs: string[] }) {
  const router = useRouter();

  useEffect(() => {
    if (!hrefs.length) return;

    const connection = (
      navigator as Navigator & {
        connection?: { saveData?: boolean; effectiveType?: string };
      }
    ).connection;

    if (connection?.saveData) return;
    if (
      connection?.effectiveType === "slow-2g" ||
      connection?.effectiveType === "2g"
    ) {
      return;
    }

    const isSlow = connection?.effectiveType === "3g";
    const limit = isSlow ? 6 : 12;
    const queue = hrefs.slice(0, limit);

    let cancelled = false;
    let index = 0;
    let timeoutId = 0;
    let idleId: number | undefined;

    const run = () => {
      if (cancelled) return;
      const chunk = queue.slice(index, index + 2);
      index += 2;
      for (const href of chunk) {
        try {
          router.prefetch(href);
        } catch {
          // ignore prefetch failures
        }
      }
      if (index < queue.length) {
        timeoutId = window.setTimeout(run, isSlow ? 320 : 160);
      }
    };

    const start = () => {
      if (cancelled) return;
      if (typeof window.requestIdleCallback === "function") {
        idleId = window.requestIdleCallback(() => run(), { timeout: 2500 });
      } else {
        timeoutId = window.setTimeout(run, isSlow ? 1800 : 1200);
      }
    };

    if (document.readyState === "complete") {
      start();
    } else {
      window.addEventListener("load", start, { once: true });
    }

    return () => {
      cancelled = true;
      window.removeEventListener("load", start);
      window.clearTimeout(timeoutId);
      if (
        idleId != null &&
        typeof window.cancelIdleCallback === "function"
      ) {
        window.cancelIdleCallback(idleId);
      }
    };
  }, [hrefs, router]);

  return null;
}
