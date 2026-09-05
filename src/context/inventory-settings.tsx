"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
  type ReactNode,
} from "react";
import {
  DEFAULT_INVENTORY_SETTINGS,
  loadInventorySettings,
  type InventorySettings,
} from "@/lib/api";

const InventorySettingsContext = createContext<InventorySettings>(
  DEFAULT_INVENTORY_SETTINGS,
);

/** Rare background check — stock visibility toggles are infrequent; focus/visibility covers the hot path. */
const POLL_MS = 5 * 60_000;

export function InventorySettingsProvider({
  initial = DEFAULT_INVENTORY_SETTINGS,
  children,
}: {
  initial?: InventorySettings;
  children: ReactNode;
}) {
  const [settings, setSettings] = useState(initial);
  const latest = useRef(initial);
  const inFlight = useRef(false);

  const revalidate = useCallback(async () => {
    if (inFlight.current || document.visibilityState === "hidden") return;
    inFlight.current = true;

    try {
      const next = await loadInventorySettings({ fresh: true });
      if (!next) return;
      if (
        latest.current.show_stock_status === next.show_stock_status &&
        latest.current.show_stock_quantity === next.show_stock_quantity
      ) {
        return;
      }
      latest.current = next;
      setSettings(next);
    } finally {
      inFlight.current = false;
    }
  }, []);

  useEffect(() => {
    void revalidate();
    const onVisible = () => {
      if (document.visibilityState === "visible") void revalidate();
    };
    const onFocus = () => void revalidate();

    document.addEventListener("visibilitychange", onVisible);
    window.addEventListener("focus", onFocus);
    window.addEventListener("pageshow", onFocus);
    const poll = window.setInterval(() => void revalidate(), POLL_MS);

    return () => {
      document.removeEventListener("visibilitychange", onVisible);
      window.removeEventListener("focus", onFocus);
      window.removeEventListener("pageshow", onFocus);
      window.clearInterval(poll);
    };
  }, [revalidate]);

  return (
    <InventorySettingsContext.Provider value={settings}>
      {children}
    </InventorySettingsContext.Provider>
  );
}

export function useInventorySettings(): InventorySettings {
  return useContext(InventorySettingsContext);
}
