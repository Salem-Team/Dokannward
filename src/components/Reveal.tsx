"use client";

import { useLayoutEffect, useRef, type CSSProperties, type ReactNode } from "react";

type RevealVariant = "fade" | "mask" | "zebra";

function alreadyInView(el: HTMLElement) {
  const rect = el.getBoundingClientRect();
  return rect.top < window.innerHeight * 0.92 && rect.bottom > 0;
}

/**
 * Editorial entrance reveal — fade / mask / bronze wipe.
 * Double-rAF ensures the browser paints the hidden state before
 * `.visible` so CSS transitions always fire. Safety timeout prevents
 * stuck invisible content.
 */
export function Reveal({
  children,
  className = "",
  delay = 0,
  variant = "fade",
}: {
  children: ReactNode;
  className?: string;
  delay?: number;
  variant?: RevealVariant;
}) {
  const ref = useRef<HTMLDivElement>(null);

  useLayoutEffect(() => {
    const el = ref.current;
    if (!el) return;

    let timer: number | undefined;
    let safety: number | undefined;
    let revealed = false;
    let frame1 = 0;
    let frame2 = 0;

    const reveal = () => {
      if (revealed) return;
      revealed = true;
      el.classList.add("visible");
      if (variant === "zebra") el.classList.add("play");
    };

    const scheduleReveal = () => {
      // Two frames: commit hidden styles, then animate in.
      frame1 = window.requestAnimationFrame(() => {
        frame2 = window.requestAnimationFrame(() => {
          if (delay > 0) {
            timer = window.setTimeout(reveal, delay);
            return;
          }
          reveal();
        });
      });
    };

    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      reveal();
      return;
    }

    if (alreadyInView(el)) {
      scheduleReveal();
      safety = window.setTimeout(reveal, 1600 + delay);
      return () => {
        if (timer) window.clearTimeout(timer);
        if (safety) window.clearTimeout(safety);
        if (frame1) window.cancelAnimationFrame(frame1);
        if (frame2) window.cancelAnimationFrame(frame2);
      };
    }

    const obs = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          scheduleReveal();
          obs.disconnect();
        }
      },
      { threshold: 0.08, rootMargin: "0px 0px 14% 0px" },
    );
    obs.observe(el);
    safety = window.setTimeout(reveal, 2400 + delay);

    return () => {
      obs.disconnect();
      if (timer) window.clearTimeout(timer);
      if (safety) window.clearTimeout(safety);
      if (frame1) window.cancelAnimationFrame(frame1);
      if (frame2) window.cancelAnimationFrame(frame2);
    };
  }, [variant, delay]);

  const variantClass =
    variant === "mask"
      ? "reveal-mask"
      : variant === "zebra"
        ? "zebra-reveal"
        : "reveal";

  const style: CSSProperties =
    variant === "fade" ? { transitionDelay: `${delay}ms` } : {};

  return (
    <div ref={ref} className={`${variantClass} ${className}`} style={style}>
      {children}
    </div>
  );
}
