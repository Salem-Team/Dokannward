"use client";

import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import {
  HomeCategoryProductRail,
} from "@/components/HomeCategoryProductRail";
import type { ProductCardData } from "@/lib/product-card";
import { IconArrowRight } from "@/components/Icons";

type RailItem = {
  product: ProductCardData;
  priceLabel: string;
};

/**
 * Category product strip under the banner — scroll-triggered editorial entrance
 * for the section head and staggered product cards.
 */
export function HomeCategoryProductsMotion({
  title,
  href,
  items,
}: {
  title: string;
  href: string;
  items: RailItem[];
}) {
  const wrapRef = useRef<HTMLDivElement>(null);
  const [ready, setReady] = useState(false);
  const [inView, setInView] = useState(false);
  const [reduceMotion, setReduceMotion] = useState(false);

  useEffect(() => {
    if (typeof window.matchMedia !== "function") return;
    const mq = window.matchMedia("(prefers-reduced-motion: reduce)");
    setReduceMotion(mq.matches);
    const onChange = () => setReduceMotion(mq.matches);
    mq.addEventListener("change", onChange);
    return () => mq.removeEventListener("change", onChange);
  }, []);

  useEffect(() => {
    const el = wrapRef.current;
    if (!el) return;

    setReady(true);

    if (reduceMotion) {
      setInView(true);
      return;
    }

    const reveal = () => setInView(true);

    const rect = el.getBoundingClientRect();
    if (rect.top < window.innerHeight * 0.92 && rect.bottom > 40) {
      reveal();
      return;
    }

    const obs = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          reveal();
          obs.disconnect();
        }
      },
      { threshold: 0.14, rootMargin: "0px 0px -6% 0px" },
    );
    obs.observe(el);
    return () => obs.disconnect();
  }, [reduceMotion, items.length]);

  if (items.length === 0) return null;

  return (
    <div
      ref={wrapRef}
      className={`home-category__products-wrap${ready ? " is-ready" : ""}${
        inView ? " is-inview" : ""
      }${reduceMotion ? " is-static" : ""}`}
    >
      <div className="home-category__products-head">
        <div className="home-category__products-heading">
          <p className="home-category__products-eyebrow">{title}</p>
          <p className="home-category__products-count">
            {items.length} {items.length === 1 ? "piece" : "pieces"}
          </p>
        </div>
        <Link href={href} className="home-category__products-link">
          Shop all
          <IconArrowRight size={11} />
        </Link>
      </div>
      <HomeCategoryProductRail items={items} label={title} />
    </div>
  );
}
