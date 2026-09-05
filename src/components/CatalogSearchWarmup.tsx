"use client";

import { useEffect } from "react";

/**
 * Warms the header search catalog during idle time so the first open feels
 * instant — without blocking first paint or competing with LCP.
 */
export function CatalogSearchWarmup() {
  useEffect(() => {
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

    const warm = () => {
      void fetch("/api/catalog-search").catch(() => {});
    };

    if (typeof window.requestIdleCallback === "function") {
      const id = window.requestIdleCallback(warm, { timeout: 10000 });
      return () => window.cancelIdleCallback(id);
    }

    const t = window.setTimeout(warm, 8000);
    return () => window.clearTimeout(t);
  }, []);

  return null;
}
