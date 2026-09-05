"use client";

import {
  useEffect,
  useRef,
  useState,
  type CSSProperties,
  type PointerEvent as ReactPointerEvent,
} from "react";
import { StorefrontImage } from "@/components/StorefrontImage";
import Link from "next/link";
import type { HomepageCategoryLogo } from "@/lib/catalog";
import { categoryPublicImageFallback, IMAGE_QUALITY } from "@/lib/media";
import { useLocale } from "@/context/locale";

const DRAG_THRESHOLD = 5;
/** Fast editorial cruise — unique logos only, no clones. */
const SPEED = 62;
const FRICTION = 0.0024;
const MIN_VELOCITY = 14;
const MAX_FLICK = 1200;
const RESUME_MS = 1100;
const EDGE_DWELL_MS = 700;
const EDGE_EASE = 0.42;

/**
 * Circular category marks — full-bleed cover photos in 50% rounds.
 * Unique set only (never duplicated). GPU ping-pong auto-scroll when overflowing.
 */
export function HomeCategoryLogos({
  logos: logosProp,
}: {
  logos: HomepageCategoryLogo[];
}) {
  const { t } = useLocale();
  const logos = dedupeLogos(logosProp);

  const scrollerRef = useRef<HTMLDivElement>(null);
  const trackRef = useRef<HTMLDivElement>(null);
  const [paused, setPaused] = useState(false);
  const [dragging, setDragging] = useState(false);
  const [reduceMotion, setReduceMotion] = useState(false);
  const [canScroll, setCanScroll] = useState(false);

  const pointerIdRef = useRef<number | null>(null);
  const dragOriginRef = useRef({ x: 0, offset: 0 });
  const didDragRef = useRef(false);
  const offsetRef = useRef(0);
  const maxOffsetRef = useRef(0);
  const directionRef = useRef<-1 | 1>(-1);
  const dwellUntilRef = useRef(0);
  const velocityRef = useRef(0);
  const lastMoveRef = useRef({ x: 0, t: 0 });
  const rafRef = useRef<number | null>(null);
  const lastTsRef = useRef<number | null>(null);
  const resumeTimerRef = useRef<number | null>(null);
  const hoverPausedRef = useRef(false);

  const scrolling = !reduceMotion && canScroll;

  useEffect(() => {
    const mq = window.matchMedia("(prefers-reduced-motion: reduce)");
    setReduceMotion(mq.matches);
    const onChange = () => setReduceMotion(mq.matches);
    mq.addEventListener("change", onChange);
    return () => mq.removeEventListener("change", onChange);
  }, []);

  useEffect(() => {
    const scroller = scrollerRef.current;
    const track = trackRef.current;
    if (!scroller || !track || logos.length === 0) return;

    const measure = () => {
      const max = Math.max(0, track.scrollWidth - scroller.clientWidth);
      maxOffsetRef.current = max;
      const nextCanScroll = max > 8;
      setCanScroll(nextCanScroll);

      if (!nextCanScroll) {
        offsetRef.current = 0;
        velocityRef.current = 0;
        directionRef.current = -1;
        if (trackRef.current) trackRef.current.style.transform = "";
        return;
      }

      let next = Math.min(0, Math.max(-max, offsetRef.current));
      offsetRef.current = next;
      track.style.transform = `translate3d(${next}px, 0, 0)`;
    };

    measure();
    const ro = new ResizeObserver(measure);
    ro.observe(scroller);
    ro.observe(track);
    window.addEventListener("resize", measure);
    return () => {
      ro.disconnect();
      window.removeEventListener("resize", measure);
    };
  }, [logos.length]);

  useEffect(() => {
    if (!scrolling) {
      if (!canScroll) {
        offsetRef.current = 0;
        velocityRef.current = 0;
        if (trackRef.current) trackRef.current.style.transform = "";
      }
      return;
    }

    const clamp = (value: number) => {
      const max = maxOffsetRef.current;
      return Math.min(0, Math.max(-max, value));
    };

    const edgeFactor = (pos: number) => {
      const max = maxOffsetRef.current;
      if (max <= 0) return 1;
      const distFromStart = Math.abs(pos);
      const distFromEnd = max + pos;
      const near = Math.min(distFromStart, distFromEnd);
      const soft = Math.min(1, near / Math.max(48, max * EDGE_EASE));
      return 0.28 + soft * 0.72;
    };

    const tick = (ts: number) => {
      if (typeof document !== "undefined" && document.visibilityState === "hidden") {
        lastTsRef.current = null;
        rafRef.current = requestAnimationFrame(tick);
        return;
      }

      if (lastTsRef.current == null) lastTsRef.current = ts;
      const dt = Math.min(40, ts - lastTsRef.current) / 1000;
      lastTsRef.current = ts;

      if (!dragging) {
        const max = maxOffsetRef.current;
        if (max > 0) {
          let next = offsetRef.current;

          if (Math.abs(velocityRef.current) > MIN_VELOCITY) {
            next += velocityRef.current * dt;
            velocityRef.current *= Math.exp(-FRICTION * (dt * 1000));
            if (Math.abs(velocityRef.current) <= MIN_VELOCITY) {
              velocityRef.current = 0;
            }
            if (next >= 0) {
              next = 0;
              velocityRef.current = 0;
              directionRef.current = -1;
              dwellUntilRef.current = ts + EDGE_DWELL_MS;
            } else if (next <= -max) {
              next = -max;
              velocityRef.current = 0;
              directionRef.current = 1;
              dwellUntilRef.current = ts + EDGE_DWELL_MS;
            }
          } else if (!paused && ts >= dwellUntilRef.current) {
            next += directionRef.current * SPEED * edgeFactor(next) * dt;
            if (next >= 0) {
              next = 0;
              directionRef.current = -1;
              dwellUntilRef.current = ts + EDGE_DWELL_MS;
            } else if (next <= -max) {
              next = -max;
              directionRef.current = 1;
              dwellUntilRef.current = ts + EDGE_DWELL_MS;
            }
          }

          next = clamp(next);
          offsetRef.current = next;
          if (trackRef.current) {
            trackRef.current.style.transform = `translate3d(${next}px, 0, 0)`;
          }
        }
      }

      rafRef.current = requestAnimationFrame(tick);
    };

    rafRef.current = requestAnimationFrame(tick);
    return () => {
      if (rafRef.current != null) cancelAnimationFrame(rafRef.current);
      rafRef.current = null;
      lastTsRef.current = null;
    };
  }, [paused, dragging, scrolling, canScroll]);

  useEffect(() => {
    return () => {
      if (resumeTimerRef.current != null) {
        window.clearTimeout(resumeTimerRef.current);
      }
    };
  }, []);

  const clearResumeTimer = () => {
    if (resumeTimerRef.current != null) {
      window.clearTimeout(resumeTimerRef.current);
      resumeTimerRef.current = null;
    }
  };

  const scheduleResume = () => {
    clearResumeTimer();
    resumeTimerRef.current = window.setTimeout(() => {
      resumeTimerRef.current = null;
      if (!hoverPausedRef.current) setPaused(false);
    }, RESUME_MS);
  };

  const onPointerDown = (e: ReactPointerEvent<HTMLDivElement>) => {
    if (!canScroll) return;
    if (e.button !== 0 && e.pointerType === "mouse") return;
    clearResumeTimer();
    pointerIdRef.current = e.pointerId;
    didDragRef.current = false;
    velocityRef.current = 0;
    dragOriginRef.current = { x: e.clientX, offset: offsetRef.current };
    lastMoveRef.current = { x: e.clientX, t: performance.now() };
    setPaused(true);
  };

  const onPointerMove = (e: ReactPointerEvent<HTMLDivElement>) => {
    if (!canScroll) return;
    if (pointerIdRef.current !== e.pointerId) return;
    const dx = e.clientX - dragOriginRef.current.x;

    if (!dragging) {
      if (Math.abs(dx) < DRAG_THRESHOLD) return;
      setDragging(true);
      didDragRef.current = true;
      try {
        e.currentTarget.setPointerCapture(e.pointerId);
      } catch {
        /* ignore */
      }
    }

    e.preventDefault();
    const now = performance.now();
    const dt = Math.max(1, now - lastMoveRef.current.t);
    const moveDx = e.clientX - lastMoveRef.current.x;
    velocityRef.current = moveDx / (dt / 1000);
    lastMoveRef.current = { x: e.clientX, t: now };

    const max = maxOffsetRef.current;
    const next = Math.min(0, Math.max(-max, dragOriginRef.current.offset + dx));
    offsetRef.current = next;
    if (trackRef.current) {
      trackRef.current.style.transform = `translate3d(${next}px, 0, 0)`;
    }
  };

  const onPointerUp = (e: ReactPointerEvent<HTMLDivElement>) => {
    if (pointerIdRef.current !== e.pointerId) return;
    pointerIdRef.current = null;
    if (velocityRef.current > MAX_FLICK) velocityRef.current = MAX_FLICK;
    if (velocityRef.current < -MAX_FLICK) velocityRef.current = -MAX_FLICK;
    if (Math.abs(velocityRef.current) > MIN_VELOCITY) {
      directionRef.current = velocityRef.current > 0 ? 1 : -1;
    }
    setDragging(false);
    try {
      e.currentTarget.releasePointerCapture(e.pointerId);
    } catch {
      /* ignore */
    }
    scheduleResume();
    window.setTimeout(() => {
      didDragRef.current = false;
    }, 0);
  };

  if (logos.length === 0) return null;

  return (
    <section
      className={`home-category-logos is-animated${
        dragging ? " is-dragging" : ""
      }${paused ? " is-paused" : ""}${scrolling ? " is-scroll" : " is-fit"}${
        reduceMotion ? " is-reduced" : ""
      }`}
      aria-label={t("category.shopAria")}
      data-count={logos.length}
      onMouseEnter={() => {
        if (!scrolling) return;
        hoverPausedRef.current = true;
        clearResumeTimer();
        setPaused(true);
      }}
      onMouseLeave={() => {
        if (!scrolling) return;
        hoverPausedRef.current = false;
        scheduleResume();
      }}
      onFocusCapture={() => {
        if (!scrolling) return;
        hoverPausedRef.current = true;
        clearResumeTimer();
        setPaused(true);
      }}
      onBlurCapture={(e) => {
        if (!e.currentTarget.contains(e.relatedTarget as Node | null)) {
          hoverPausedRef.current = false;
          scheduleResume();
        }
      }}
    >
      <div className="container home-category-logos__inner">
          <div
            ref={scrollerRef}
            className="home-category-logos__scroller"
            onPointerDown={onPointerDown}
            onPointerMove={onPointerMove}
            onPointerUp={onPointerUp}
            onPointerCancel={onPointerUp}
          >
            <div
              ref={trackRef}
              className="home-category-logos__track"
              style={{ "--hc-logo-n": logos.length } as CSSProperties}
            >
              <ul className="home-category-logos__rail">
                {logos.map((item, i) => (
                  <li
                    key={item.handle}
                    className="home-category-logos__item"
                    style={{ "--hc-logo-i": i } as CSSProperties}
                  >
                    <Link
                      href={item.href}
                      className="home-category-logos__link"
                      aria-label={t("category.shopNamed", { title: item.title })}
                      onClick={(e) => {
                        if (didDragRef.current) e.preventDefault();
                      }}
                    >
                      <span className="home-category-logos__frame">
                        <span
                          className="home-category-logos__ring"
                          aria-hidden="true"
                        />
                        <span
                          className="home-category-logos__shine"
                          aria-hidden="true"
                        />
                        <span className="home-category-logos__stage">
                          <StorefrontImage
                            src={item.logo}
                            fallbacks={[
                              categoryPublicImageFallback(item.logo),
                              `/images/categories/${item.handle}.webp`,
                              `/images/categories/${item.handle}.png`,
                            ]}
                            alt=""
                            fill
                            sizes="(max-width: 749px) 42vw, (max-width: 1100px) 22vw, 260px"
                            quality={IMAGE_QUALITY.card}
                            priority={false}
                            loading={i < 2 ? "eager" : "lazy"}
                            className="home-category-logos__image"
                          />
                        </span>
                      </span>
                      <span className="home-category-logos__meta">
                        <span className="home-category-logos__name">
                          {item.title}
                        </span>
                        <span
                          className="home-category-logos__rule"
                          aria-hidden="true"
                        />
                      </span>
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          </div>
      </div>
    </section>
  );
}

function dedupeLogos(logos: HomepageCategoryLogo[]): HomepageCategoryLogo[] {
  const seen = new Set<string>();
  const out: HomepageCategoryLogo[] = [];
  for (const logo of logos) {
    const key = logo.handle || logo.href || logo.title;
    if (!key || seen.has(key)) continue;
    seen.add(key);
    out.push(logo);
  }
  return out;
}
