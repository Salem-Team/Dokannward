"use client";

import { StorefrontImage } from "@/components/StorefrontImage";
import Link from "next/link";
import {
  useEffect,
  useMemo,
  useRef,
  useState,
  type CSSProperties,
  type PointerEvent as ReactPointerEvent,
} from "react";
import { useLocale } from "@/context/locale";

type Category = {
  title: string;
  handle: string;
  image: string;
  href: string;
};

/** px per second */
const AUTO_SPEED = 34;
const IDLE_RESUME_MS = 900;
const DRAG_THRESHOLD = 6;

function uniqueCategories(categories: Category[]) {
  const seen = new Set<string>();
  return categories.filter((cat) => {
    const key = cat.handle || cat.title;
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}

export function ShopCollectionsMarquee({
  categories,
}: {
  categories: Category[];
}) {
  const { t } = useLocale();
  const items = useMemo(() => uniqueCategories(categories), [categories]);
  const viewportRef = useRef<HTMLDivElement>(null);
  const trackRef = useRef<HTMLDivElement>(null);
  const offsetRef = useRef(0);
  const maxOffsetRef = useRef(0);
  const dirRef = useRef(1);
  const pausedRef = useRef(false);
  const draggingRef = useRef(false);
  const canScrollRef = useRef(false);
  const resumeTimerRef = useRef<number | null>(null);
  const pointerIdRef = useRef<number | null>(null);
  const dragOriginRef = useRef({ x: 0, offset: 0 });
  const didDragRef = useRef(false);
  const [revealed, setRevealed] = useState(false);
  const [dragging, setDragging] = useState(false);
  const [canScroll, setCanScroll] = useState(false);

  useEffect(() => {
    const el = viewportRef.current;
    if (!el) return;
    const io = new IntersectionObserver(
      ([entry]) => {
        if (!entry?.isIntersecting) return;
        setRevealed(true);
        io.disconnect();
      },
      { threshold: 0.15 },
    );
    io.observe(el);
    return () => io.disconnect();
  }, []);

  useEffect(() => {
    const viewport = viewportRef.current;
    const track = trackRef.current;
    if (!viewport || !track) return;
    const reduced = window.matchMedia(
      "(prefers-reduced-motion: reduce)",
    ).matches;

    const apply = (value: number) => {
      const max = maxOffsetRef.current;
      const next = Math.max(0, Math.min(max, value));
      offsetRef.current = next;
      if (canScrollRef.current) {
        track.style.transform = `translate3d(${-next}px, 0, 0)`;
      } else {
        track.style.transform = "";
      }
      return next;
    };

    const measure = () => {
      // Temporarily clear transform so scrollWidth is accurate
      const prev = track.style.transform;
      track.style.transform = "";
      const overflow = track.scrollWidth - viewport.clientWidth;
      track.style.transform = prev;

      const scrolling = overflow > 8;
      canScrollRef.current = scrolling;
      setCanScroll(scrolling);
      maxOffsetRef.current = scrolling ? overflow : 0;

      if (!scrolling) {
        offsetRef.current = 0;
        track.style.transform = "";
        return;
      }

      apply(offsetRef.current);
    };

    measure();
    const ro = new ResizeObserver(measure);
    ro.observe(track);
    ro.observe(viewport);

    if (reduced) {
      return () => ro.disconnect();
    }

    let raf = 0;
    let last = performance.now();

    const tick = (now: number) => {
      const dt = Math.min(40, now - last);
      last = now;

      const max = maxOffsetRef.current;
      if (
        canScrollRef.current &&
        !pausedRef.current &&
        !draggingRef.current &&
        max > 1
      ) {
        let next =
          offsetRef.current + dirRef.current * ((AUTO_SPEED * dt) / 1000);

        if (next >= max) {
          next = max;
          dirRef.current = -1;
        } else if (next <= 0) {
          next = 0;
          dirRef.current = 1;
        }

        apply(next);
      }

      raf = requestAnimationFrame(tick);
    };

    raf = requestAnimationFrame(tick);

    return () => {
      cancelAnimationFrame(raf);
      ro.disconnect();
      if (resumeTimerRef.current) window.clearTimeout(resumeTimerRef.current);
    };
  }, [items.length]);

  const pause = () => {
    if (!canScrollRef.current) return;
    pausedRef.current = true;
    if (resumeTimerRef.current) {
      window.clearTimeout(resumeTimerRef.current);
      resumeTimerRef.current = null;
    }
  };

  const scheduleResume = () => {
    if (!canScrollRef.current) return;
    if (resumeTimerRef.current) window.clearTimeout(resumeTimerRef.current);
    resumeTimerRef.current = window.setTimeout(() => {
      if (!draggingRef.current) pausedRef.current = false;
      resumeTimerRef.current = null;
    }, IDLE_RESUME_MS);
  };

  const onPointerDown = (e: ReactPointerEvent<HTMLDivElement>) => {
    if (!canScrollRef.current) return;
    if (e.pointerType !== "mouse" || e.button !== 0) return;
    pause();
    pointerIdRef.current = e.pointerId;
    didDragRef.current = false;
    dragOriginRef.current = { x: e.clientX, offset: offsetRef.current };
  };

  const onPointerMove = (e: ReactPointerEvent<HTMLDivElement>) => {
    if (pointerIdRef.current !== e.pointerId) return;
    const track = trackRef.current;
    if (!track) return;

    const dx = e.clientX - dragOriginRef.current.x;
    if (!draggingRef.current) {
      if (Math.abs(dx) < DRAG_THRESHOLD) return;
      draggingRef.current = true;
      didDragRef.current = true;
      setDragging(true);
      try {
        viewportRef.current?.setPointerCapture(e.pointerId);
      } catch {
        /* ignore */
      }
    }

    e.preventDefault();
    const max = maxOffsetRef.current;
    const next = Math.max(
      0,
      Math.min(max, dragOriginRef.current.offset - dx),
    );
    offsetRef.current = next;
    track.style.transform = `translate3d(${-next}px, 0, 0)`;
  };

  const onPointerUp = (e: ReactPointerEvent<HTMLDivElement>) => {
    if (pointerIdRef.current !== e.pointerId) return;
    pointerIdRef.current = null;
    draggingRef.current = false;
    setDragging(false);
    try {
      viewportRef.current?.releasePointerCapture(e.pointerId);
    } catch {
      /* ignore */
    }
    scheduleResume();
    window.setTimeout(() => {
      didDragRef.current = false;
    }, 0);
  };

  return (
    <section
      className={`shop-orbit${revealed ? " is-revealed" : ""}${
        dragging ? " is-dragging" : ""
      }${canScroll ? " is-scrollable" : " is-static"}`}
      aria-label={t("collections.marquee")}
      onMouseEnter={pause}
      onMouseLeave={scheduleResume}
      onTouchStart={pause}
      onTouchEnd={scheduleResume}
    >
      <div
        ref={viewportRef}
        className="shop-orbit__scroller"
        onPointerDown={onPointerDown}
        onPointerMove={onPointerMove}
        onPointerUp={onPointerUp}
        onPointerCancel={onPointerUp}
      >
        <div ref={trackRef} className="shop-orbit__row">
          {items.map((cat, i) => (
            <Link
              key={cat.handle}
              href={cat.href}
              className="shop-orbit__item"
              style={{ "--reveal-delay": `${i * 70}ms` } as CSSProperties}
              draggable={false}
              onClick={(e) => {
                if (didDragRef.current) {
                  e.preventDefault();
                  e.stopPropagation();
                }
              }}
            >
              <div className="shop-orbit__orb">
                <div className="shop-orbit__orb-inner">
                  <StorefrontImage
                    src={cat.image}
                    alt={cat.title}
                    width={320}
                    height={320}
                    sizes="(min-width: 900px) 140px, 120px"
                    className="shop-orbit__image"
                    draggable={false}
                    loading={i > 1 ? "lazy" : "eager"}
                  />
                </div>
              </div>
              <h3 className="shop-orbit__title heading">{cat.title}</h3>
            </Link>
          ))}
        </div>
      </div>
    </section>
  );
}
