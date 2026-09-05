"use client";

import { StorefrontImage } from "@/components/StorefrontImage";
import Link from "next/link";
import {
  useEffect,
  useRef,
  useState,
  type CSSProperties,
  type KeyboardEvent as ReactKeyboardEvent,
} from "react";
import { IMAGE_QUALITY } from "@/lib/media";
import { IconArrowRight } from "@/components/Icons";
import { useLocale } from "@/context/locale";

export type AnimatedCollection = {
  handle: string;
  title: string;
  href: string;
  image: string;
  description: string | null;
  products_count: number;
  categories: string[];
  previews: { src: string; alt: string }[];
};

const DWELL_MS = 5200;
const TRANSITION_MS = 780;

export function CollectionsAnimatedStories({
  collections,
}: {
  collections: AnimatedCollection[];
}) {
  const { t } = useLocale();
  const count = collections.length;
  const [active, setActive] = useState(0);
  const [paused, setPaused] = useState(false);
  const [reducedMotion, setReducedMotion] = useState(false);
  const [progressKey, setProgressKey] = useState(0);
  const rootRef = useRef<HTMLDivElement>(null);
  const countRef = useRef(count);
  countRef.current = count;
  const activeRef = useRef(active);
  activeRef.current = active;

  const goTo = (index: number) => {
    const total = countRef.current;
    if (total < 1) return;
    const next = ((index % total) + total) % total;
    setActive(next);
    setProgressKey((k) => k + 1);
  };

  useEffect(() => {
    const mq = window.matchMedia("(prefers-reduced-motion: reduce)");
    const sync = () => setReducedMotion(mq.matches);
    sync();
    mq.addEventListener("change", sync);
    return () => mq.removeEventListener("change", sync);
  }, []);

  useEffect(() => {
    if (reducedMotion || paused || count < 2) return;
    const id = window.setInterval(() => {
      goTo(activeRef.current + 1);
    }, DWELL_MS);
    return () => window.clearInterval(id);
  }, [paused, reducedMotion, count]);

  if (count === 0) return null;

  const onKeyDown = (e: ReactKeyboardEvent<HTMLDivElement>) => {
    if (count < 2) return;
    if (e.key === "ArrowRight") {
      e.preventDefault();
      goTo(active + 1);
    } else if (e.key === "ArrowLeft") {
      e.preventDefault();
      goTo(active - 1);
    }
  };

  return (
    <div
      ref={rootRef}
      className={`collections-run${paused ? " is-paused" : ""}${
        reducedMotion ? " is-reduced" : ""
      }`}
      onMouseEnter={() => setPaused(true)}
      onMouseLeave={() => setPaused(false)}
      onFocusCapture={() => setPaused(true)}
      onBlurCapture={(e) => {
        if (!rootRef.current?.contains(e.relatedTarget as Node | null)) {
          setPaused(false);
        }
      }}
      onKeyDown={onKeyDown}
      tabIndex={0}
      role="region"
      aria-roledescription="carousel"
      aria-label={t("collections.title")}
    >
      <div className="collections-run__stage" aria-live="polite">
        {collections.map((collection, i) => (
          <CollectionSlide
            key={collection.handle}
            collection={collection}
            index={i}
            active={i === active}
            priority={i === 0}
            kindLabel={t("home.collection.kind")}
          />
        ))}
      </div>

      {count > 1 ? (
        <div className="collections-run__chrome">
          <div className="collections-run__progress" aria-hidden="true">
            <span
              key={progressKey}
              className="collections-run__progress-bar"
              style={
                reducedMotion
                  ? undefined
                  : ({
                      animationDuration: `${DWELL_MS}ms`,
                    } as CSSProperties)
              }
            />
          </div>

          <div className="collections-run__nav">
            <button
              type="button"
              className="collections-run__arrow"
              aria-label={t("a11y.prevCollection")}
              onClick={() => goTo(active - 1)}
            >
              <IconArrowRight
                size={14}
                className="collections-run__arrow-icon--prev"
              />
            </button>

            <ol className="collections-run__dots" aria-label={t("a11y.chooseCollection")}>
              {collections.map((c, i) => (
                <li key={c.handle}>
                  <button
                    type="button"
                    className={`collections-run__dot${
                      i === active ? " is-active" : ""
                    }`}
                    aria-label={c.title}
                    aria-current={i === active ? "true" : undefined}
                    onClick={() => goTo(i)}
                  />
                </li>
              ))}
            </ol>

            <button
              type="button"
              className="collections-run__arrow"
              aria-label={t("a11y.nextCollection")}
              onClick={() => goTo(active + 1)}
            >
              <IconArrowRight size={14} />
            </button>
          </div>
        </div>
      ) : null}
    </div>
  );
}

function CollectionSlide({
  collection,
  index,
  active,
  priority,
  kindLabel,
}: {
  collection: AnimatedCollection;
  index: number;
  active: boolean;
  priority?: boolean;
  kindLabel: string;
}) {
  const { t } = useLocale();
  const number = String(index + 1).padStart(2, "0");
  const flipped = index % 2 === 1;
  const countLabel =
    collection.products_count === 1
      ? t("home.collection.piece")
      : t("home.collection.pieces", { count: collection.products_count });

  const description =
    collection.description ||
    (collection.categories.length > 0
      ? t("home.collection.descCategories", {
          categories: collection.categories.join(", "),
        })
      : t("home.collection.descDefault"));

  return (
    <article
      className={`home-collections__story collections-run__slide${
        flipped ? " home-collections__story--flip" : ""
      }${active ? " is-active" : ""}`}
      aria-hidden={!active}
      inert={!active}
      style={
        {
          "--slide-duration": `${TRANSITION_MS}ms`,
        } as CSSProperties
      }
    >
      <div className="home-collections__media-reveal">
        <Link
          href={collection.href}
          prefetch={active}
          tabIndex={active ? 0 : -1}
          className="home-collections__cover"
          aria-label={`${collection.title} — ${countLabel}`}
        >
          <StorefrontImage
            src={collection.image}
            alt=""
            fill
            sizes="(max-width: 900px) 100vw, 58vw"
            quality={IMAGE_QUALITY.hero}
            priority={priority}
            loading={priority ? "eager" : "lazy"}
            className="home-collections__cover-image media-mono"
          />
          <div className="home-collections__cover-veil" aria-hidden="true" />
          <span className="home-collections__cover-index" aria-hidden="true">
            {number}
          </span>
        </Link>
      </div>

      <div className="home-collections__panel-reveal">
        <div className="home-collections__panel">
          <p className="home-collections__kind">{kindLabel}</p>
          <h3 className="heading home-collections__name">
            <Link
              href={collection.href}
              prefetch={active}
              tabIndex={active ? 0 : -1}
            >
              {collection.title}
            </Link>
          </h3>

          <p className="home-collections__desc">{description}</p>

          {collection.categories.length > 0 ? (
            <ul
              className="home-collections__facets"
              aria-label={t("a11y.insideCollection")}
            >
              {collection.categories.map((name) => (
                <li key={name} className="home-collections__facet">
                  {name}
                </li>
              ))}
            </ul>
          ) : null}

          <div className="home-collections__meta">
            <span className="home-collections__count">{countLabel}</span>
            <Link
              href={collection.href}
              prefetch={active}
              tabIndex={active ? 0 : -1}
              className="home-collections__cta"
            >
              {t("home.collection.shop")}
              <IconArrowRight size={12} />
            </Link>
          </div>

          {collection.previews.length > 0 ? (
            <div className="home-collections__previews" aria-hidden="true">
              {collection.previews.map((preview, j) => (
                <div
                  key={`${collection.handle}-p-${j}`}
                  className="home-collections__preview"
                >
                  <StorefrontImage
                    src={preview.src}
                    alt=""
                    fill
                    sizes="120px"
                    quality={IMAGE_QUALITY.card}
                    className="home-collections__preview-image media-mono"
                  />
                </div>
              ))}
            </div>
          ) : null}
        </div>
      </div>
    </article>
  );
}
