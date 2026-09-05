"use client";

import {
  useEffect,
  useRef,
  useState,
  type CSSProperties,
  type PointerEvent as ReactPointerEvent,
} from "react";
import {
  ProductCardClient,
  type ProductCardData,
} from "@/components/ProductCardClient";

const DRAG_THRESHOLD = 5;
const SPEED = 48;
const FRICTION = 0.0022;
const MIN_VELOCITY = 16;
const MAX_FLICK = 1400;
const RESUME_MS = 900;

type RailItem = {
  product: ProductCardData;
  priceLabel: string;
};

/**
 * Site-width product run for /collections — seamless auto-scroll with
 * drag, flick inertia, wheel scrub, and pause on hover/focus.
 */
export function CollectionsProductMarquee({
  items: itemsProp,
  label = "Collection pieces",
}: {
  items: RailItem[];
  label?: string;
}) {
  const items = dedupeItems(itemsProp);

  const scrollerRef = useRef<HTMLDivElement>(null);
  const trackRef = useRef<HTMLDivElement>(null);
  const setRef = useRef<HTMLDivElement>(null);
  const [paused, setPaused] = useState(false);
  const [dragging, setDragging] = useState(false);
  const [reduceMotion, setReduceMotion] = useState(false);
  const [copies, setCopies] = useState(3);

  const pointerIdRef = useRef<number | null>(null);
  const dragOriginRef = useRef({ x: 0, offset: 0 });
  const didDragRef = useRef(false);
  const offsetRef = useRef(0);
  const loopWidthRef = useRef(0);
  const velocityRef = useRef(0);
  const lastMoveRef = useRef({ x: 0, t: 0 });
  const rafRef = useRef<number | null>(null);
  const lastTsRef = useRef<number | null>(null);
  const resumeTimerRef = useRef<number | null>(null);
  const hoverPausedRef = useRef(false);

  const looping = !reduceMotion && items.length >= 1;

  useEffect(() => {
    const mq = window.matchMedia("(prefers-reduced-motion: reduce)");
    setReduceMotion(mq.matches);
    const onChange = () => setReduceMotion(mq.matches);
    mq.addEventListener("change", onChange);
    return () => mq.removeEventListener("change", onChange);
  }, []);

  useEffect(() => {
    const scroller = scrollerRef.current;
    const setEl = setRef.current;
    const track = trackRef.current;
    if (!scroller || !setEl || items.length === 0) return;

    const measure = () => {
      const setWidth = setEl.getBoundingClientRect().width;
      const viewWidth = scroller.clientWidth;
      const gap = track
        ? parseFloat(
            getComputedStyle(track).columnGap || getComputedStyle(track).gap,
          ) || 0
        : 0;
      const loopWidth = setWidth + gap;
      loopWidthRef.current = loopWidth;

      if (loopWidth > 0) {
        // Always cover the viewport + one extra lap so the rail never shows a blank gap.
        const needed = Math.max(
          3,
          Math.ceil((viewWidth * 2.75) / Math.max(loopWidth, 1)) + 2,
        );
        setCopies((prev) => (prev === needed ? prev : needed));
        if (looping) {
          let next = offsetRef.current % loopWidth;
          if (next > 0) next -= loopWidth;
          if (next <= -loopWidth) next += loopWidth;
          offsetRef.current = next;
          if (trackRef.current) {
            trackRef.current.style.transform = `translate3d(${next}px, 0, 0)`;
          }
        } else {
          offsetRef.current = 0;
          velocityRef.current = 0;
          if (trackRef.current) trackRef.current.style.transform = "";
        }
      } else {
        setCopies(3);
      }
    };

    measure();
    const ro = new ResizeObserver(measure);
    ro.observe(scroller);
    ro.observe(setEl);
    window.addEventListener("resize", measure);
    return () => {
      ro.disconnect();
      window.removeEventListener("resize", measure);
    };
  }, [items.length, copies, looping]);

  useEffect(() => {
    if (!looping) {
      offsetRef.current = 0;
      velocityRef.current = 0;
      if (trackRef.current) trackRef.current.style.transform = "";
      return;
    }

    const wrap = (value: number) => {
      const loop = loopWidthRef.current;
      if (loop <= 0) return value;
      let next = value;
      while (next <= -loop) next += loop;
      while (next > 0) next -= loop;
      return next;
    };

    const tick = (ts: number) => {
      if (
        typeof document !== "undefined" &&
        document.visibilityState === "hidden"
      ) {
        lastTsRef.current = null;
        rafRef.current = requestAnimationFrame(tick);
        return;
      }

      if (lastTsRef.current == null) lastTsRef.current = ts;
      const dt = Math.min(48, ts - lastTsRef.current) / 1000;
      lastTsRef.current = ts;

      if (!dragging) {
        const loop = loopWidthRef.current;
        if (loop > 0) {
          let next = offsetRef.current;
          if (Math.abs(velocityRef.current) > MIN_VELOCITY) {
            next += velocityRef.current * dt;
            velocityRef.current *= Math.exp(-FRICTION * (dt * 1000));
            if (Math.abs(velocityRef.current) <= MIN_VELOCITY) {
              velocityRef.current = 0;
            }
          } else if (!paused) {
            next -= SPEED * dt;
          }
          next = wrap(next);
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
  }, [paused, dragging, looping]);

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
    if (!looping) return;
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
    if (!looping) return;
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

    const loop = loopWidthRef.current || 1;
    let next = dragOriginRef.current.offset + dx;
    while (next > 0) next -= loop;
    while (next <= -loop) next += loop;
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

  useEffect(() => {
    const el = scrollerRef.current;
    if (!el || !looping) return;
    const onWheel = (e: WheelEvent) => {
      const dominant =
        Math.abs(e.deltaX) > Math.abs(e.deltaY) ? e.deltaX : e.deltaY;
      if (Math.abs(dominant) < 0.5) return;
      e.preventDefault();
      clearResumeTimer();
      setPaused(true);
      const loop = loopWidthRef.current || 1;
      let next = offsetRef.current - dominant;
      while (next > 0) next -= loop;
      while (next <= -loop) next += loop;
      offsetRef.current = next;
      velocityRef.current = 0;
      if (trackRef.current) {
        trackRef.current.style.transform = `translate3d(${next}px, 0, 0)`;
      }
      scheduleResume();
    };
    el.addEventListener("wheel", onWheel, { passive: false });
    return () => el.removeEventListener("wheel", onWheel);
  }, [looping, items.length]);

  if (items.length === 0) return null;

  const loopCount = Math.max(copies, 3);
  const loopSets = Array.from({ length: loopCount }, (_, i) => i);

  return (
    <section
      className={`collections-marquee${dragging ? " is-dragging" : ""}${
        paused ? " is-paused" : ""
      }${!looping ? " is-static" : ""}`}
      aria-label={label}
      onMouseEnter={() => {
        if (!looping) return;
        hoverPausedRef.current = true;
        clearResumeTimer();
        setPaused(true);
      }}
      onMouseLeave={() => {
        if (!looping) return;
        hoverPausedRef.current = false;
        scheduleResume();
      }}
      onFocusCapture={() => {
        if (!looping) return;
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
      <div
        ref={scrollerRef}
        className="collections-marquee__scroller"
        onPointerDown={onPointerDown}
        onPointerMove={onPointerMove}
        onPointerUp={onPointerUp}
        onPointerCancel={onPointerUp}
      >
        <div
          ref={trackRef}
          className="collections-marquee__rail"
          style={{ "--cm-n": items.length } as CSSProperties}
        >
          {loopSets.map((setIndex) => (
            <div
              key={`set-${setIndex}`}
              ref={setIndex === 0 ? setRef : undefined}
              className="collections-marquee__set"
              aria-hidden={setIndex > 0 ? true : undefined}
              {...(setIndex > 0 ? { inert: true } : {})}
            >
              {items.map(({ product, priceLabel }, i) => {
                const isClone = setIndex > 0;
                return (
                  <div
                    key={`${setIndex}-${product.id || product.handle}`}
                    className="collections-marquee__item"
                    style={{ "--cm-i": i } as CSSProperties}
                    onClickCapture={(e) => {
                      if (didDragRef.current) {
                        e.preventDefault();
                        e.stopPropagation();
                      }
                    }}
                  >
                    <ProductCardClient
                      product={product}
                      priceLabel={priceLabel}
                      priority={!isClone && i < 3}
                    />
                  </div>
                );
              })}
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

function dedupeItems(items: RailItem[]): RailItem[] {
  const seen = new Set<string>();
  const out: RailItem[] = [];
  for (const item of items) {
    const key = item.product.id || item.product.handle;
    if (!key || seen.has(key)) continue;
    seen.add(key);
    out.push(item);
  }
  return out;
}
