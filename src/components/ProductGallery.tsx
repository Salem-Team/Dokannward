"use client";

import { StorefrontImage } from "@/components/StorefrontImage";
import { IconChevronLeft, IconChevronRight } from "@/components/Icons";
import { useLocale } from "@/context/locale";
import { IMAGE_QUALITY } from "@/lib/media";
import {
  useCallback,
  useEffect,
  useRef,
  useState,
  type ReactNode,
} from "react";

type ProductGalleryProps = {
  images: string[];
  alt: string;
  /** When set (e.g. color swatch photo), overrides the selected gallery slide. */
  overrideImage?: string | null;
  chip?: ReactNode;
  onSelectGalleryImage?: () => void;
};

export function ProductGallery({
  images,
  alt,
  overrideImage = null,
  chip,
  onSelectGalleryImage,
}: ProductGalleryProps) {
  const { t } = useLocale();
  const [index, setIndex] = useState(0);
  const touchStartX = useRef<number | null>(null);

  const count = images.length;
  const safeIndex = count > 0 ? Math.min(index, count - 1) : 0;
  const galleryImage = count > 0 ? images[safeIndex] : null;
  const activeImage = overrideImage || galleryImage;
  const showNav = count > 1 && !overrideImage;

  const galleryKey = images.join("|");
  useEffect(() => {
    setIndex(0);
  }, [galleryKey]);

  const go = useCallback(
    (next: number) => {
      if (count < 2) return;
      setIndex((next + count) % count);
      onSelectGalleryImage?.();
    },
    [count, onSelectGalleryImage],
  );

  const selectThumb = (i: number) => {
    setIndex(i);
    onSelectGalleryImage?.();
  };

  if (!activeImage) {
    return (
      <div className="product-gallery">
        <div className="product-hero-media product-gallery__stage bg-[var(--color-paper)] aspect-[4/5] overflow-hidden relative rounded-xl border border-black/[0.07]" />
      </div>
    );
  }

  return (
    <div className="product-gallery">
      <div
        className="product-hero-media product-gallery__stage bg-[var(--color-paper)] aspect-[4/5] overflow-hidden relative rounded-xl border border-black/[0.07]"
        onTouchStart={(e) => {
          touchStartX.current = e.changedTouches[0]?.clientX ?? null;
        }}
        onTouchEnd={(e) => {
          if (touchStartX.current == null || !showNav) return;
          const endX = e.changedTouches[0]?.clientX;
          if (endX == null) return;
          const delta = endX - touchStartX.current;
          touchStartX.current = null;
          if (Math.abs(delta) < 40) return;
          go(safeIndex + (delta < 0 ? 1 : -1));
        }}
      >
        <StorefrontImage
          key={activeImage}
          src={activeImage}
          alt={alt}
          fill
          className="product-hero-media__image"
          priority
          fetchPriority="high"
          quality={IMAGE_QUALITY.pdp}
          sizes="(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 640px"
        />

        {chip}

        {showNav ? (
          <>
            <button
              type="button"
              className="product-gallery__nav product-gallery__nav--prev"
              aria-label={t("a11y.prevImage")}
              onClick={() => go(safeIndex - 1)}
            >
              <IconChevronLeft size={18} />
            </button>
            <button
              type="button"
              className="product-gallery__nav product-gallery__nav--next"
              aria-label={t("a11y.nextImage")}
              onClick={() => go(safeIndex + 1)}
            >
              <IconChevronRight size={18} />
            </button>
            <div className="product-gallery__counter" aria-live="polite">
              {safeIndex + 1} / {count}
            </div>
          </>
        ) : null}
      </div>

      {count > 1 ? (
        <div
          className="product-gallery__thumbs"
          role="tablist"
          aria-label={t("a11y.productImages")}
        >
          {images.map((src, i) => {
            const selected = !overrideImage && i === safeIndex;
            return (
              <button
                key={`${src}-${i}`}
                type="button"
                role="tab"
                aria-selected={selected}
                aria-label={`View image ${i + 1}`}
                className={[
                  "product-gallery__thumb",
                  selected ? "is-selected" : "",
                  overrideImage ? "is-dimmed" : "",
                ]
                  .filter(Boolean)
                  .join(" ")}
                onClick={() => selectThumb(i)}
              >
                <StorefrontImage
                  src={src}
                  alt=""
                  fill
                  className="product-gallery__thumb-image"
                  quality={IMAGE_QUALITY.card}
                  sizes="72px"
                />
              </button>
            );
          })}
        </div>
      ) : null}
    </div>
  );
}
