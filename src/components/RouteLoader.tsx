"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { usePathname, useSearchParams } from "next/navigation";
import { BrandLoader } from "@/components/BrandLoader";
import { useBrand } from "@/context/brand";
import { useLocale } from "@/context/locale";

type Phase = "idle" | "in" | "out";

/** Full brand veil only appears if navigation is still pending after this. */
const SHOW_AFTER_MS = 320;
/** Must cover `.route-veil--out` animation (0.58s). */
const EXIT_MS = 580;
/** Hard cap — click/popstate can fire without a committed route change. */
const SAFETY_MS = 4000;

function routeKeyFrom(pathname: string, search: string) {
  return `${pathname}?${search}`;
}

function browserRouteKey() {
  return routeKeyFrom(
    window.location.pathname,
    window.location.search.replace(/^\?/, ""),
  );
}

function isInternalNav(anchor: HTMLAnchorElement, event: MouseEvent) {
  if (event.defaultPrevented) return false;
  if (event.button !== 0) return false;
  if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false;
  if (anchor.target && anchor.target !== "_self") return false;
  if (anchor.hasAttribute("download")) return false;

  const href = anchor.getAttribute("href");
  if (!href || href.startsWith("#") || href.startsWith("mailto:") || href.startsWith("tel:")) {
    return false;
  }

  let url: URL;
  try {
    url = new URL(href, window.location.href);
  } catch {
    return false;
  }

  if (url.origin !== window.location.origin) return false;
  if (
    url.pathname === window.location.pathname &&
    url.search === window.location.search
  ) {
    return false;
  }

  return true;
}

export function RouteLoader() {
  const brand = useBrand();
  const { t } = useLocale();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const [phase, setPhase] = useState<Phase>("idle");
  const [progress, setProgress] = useState(0);

  const hideTimer = useRef<number | null>(null);
  const showTimer = useRef<number | null>(null);
  const tickTimer = useRef<number | null>(null);
  const safetyTimer = useRef<number | null>(null);
  const pending = useRef(false);
  const visible = useRef(false);
  /** Bumped on every start/finish so stale timers cannot revive the veil. */
  const gen = useRef(0);

  const routeKey = routeKeyFrom(pathname, searchParams?.toString() ?? "");
  const routeKeyRef = useRef(routeKey);
  const prevRoute = useRef(routeKey);

  routeKeyRef.current = routeKey;

  const clearTimers = useCallback(() => {
    if (hideTimer.current) window.clearTimeout(hideTimer.current);
    if (showTimer.current) window.clearTimeout(showTimer.current);
    if (tickTimer.current) window.clearInterval(tickTimer.current);
    if (safetyTimer.current) window.clearTimeout(safetyTimer.current);
    hideTimer.current = null;
    showTimer.current = null;
    tickTimer.current = null;
    safetyTimer.current = null;
  }, []);

  const finishNav = useCallback(() => {
    // Invalidate any start() generation still in flight (show/safety timers).
    gen.current += 1;
    pending.current = false;
    clearTimers();

    if (!visible.current) {
      setPhase("idle");
      setProgress(0);
      return;
    }

    setProgress(100);
    setPhase("out");
    const exitGen = gen.current;
    hideTimer.current = window.setTimeout(() => {
      if (gen.current !== exitGen) return;
      visible.current = false;
      setPhase("idle");
      setProgress(0);
    }, EXIT_MS);
  }, [clearTimers]);

  const start = useCallback(() => {
    const id = ++gen.current;
    pending.current = true;
    clearTimers();

    const reveal = () => {
      if (gen.current !== id || !pending.current) return;
      visible.current = true;
      setPhase("in");
      setProgress((p) => (p > 0 ? Math.min(p, 40) : 14));
      tickTimer.current = window.setInterval(() => {
        if (gen.current !== id) return;
        setProgress((p) => {
          if (p >= 88) return p;
          return Math.min(88, p + Math.max(1.5, (92 - p) * 0.06));
        });
      }, 160);
    };

    // Keep veil up across chained navigations; delay only the first paint.
    if (visible.current) {
      reveal();
    } else {
      showTimer.current = window.setTimeout(reveal, SHOW_AFTER_MS);
    }

    safetyTimer.current = window.setTimeout(() => {
      if (gen.current !== id) return;
      finishNav();
    }, SAFETY_MS);
  }, [clearTimers, finishNav]);

  useEffect(() => {
    const onClick = (event: MouseEvent) => {
      const target = event.target as Element | null;
      const anchor = target?.closest?.("a");
      if (!anchor || !(anchor instanceof HTMLAnchorElement)) return;
      if (!isInternalNav(anchor, event)) return;
      start();
    };

    /**
     * App Router often commits `usePathname` BEFORE `popstate` fires on
     * back/forward. Starting after that left the veil pending forever because
     * `routeKey` no longer changes and finish never re-runs.
     */
    const onPopState = () => {
      if (routeKeyRef.current === browserRouteKey()) return;
      start();
    };

    document.addEventListener("click", onClick, true);
    window.addEventListener("popstate", onPopState);
    return () => {
      document.removeEventListener("click", onClick, true);
      window.removeEventListener("popstate", onPopState);
      clearTimers();
    };
  }, [start, clearTimers]);

  useEffect(() => {
    if (prevRoute.current === routeKey) return;
    prevRoute.current = routeKey;
    finishNav();
  }, [routeKey, finishNav]);

  useEffect(() => {
    const onPageShow = (event: PageTransitionEvent) => {
      if (event.persisted) finishNav();
    };
    window.addEventListener("pageshow", onPageShow);
    return () => window.removeEventListener("pageshow", onPageShow);
  }, [finishNav]);

  if (phase === "idle") return null;

  return (
    <div
      className={`route-veil route-veil--${phase}`}
      aria-hidden={phase === "out"}
      data-route-loader=""
    >
      <div className="route-veil__progress" aria-hidden="true">
        <span style={{ transform: `scaleX(${Math.max(progress, 12) / 100})` }} />
      </div>

      <div className="route-veil__curtain" aria-hidden="true">
        <span />
        <span />
        <span />
        <span />
        <span />
        <span />
        <span />
      </div>

      <div className="route-veil__panel">
        <BrandLoader
          label={t("loading.page")}
          logoSrc={brand.logo}
          brandName={brand.name}
        />
      </div>
    </div>
  );
}
