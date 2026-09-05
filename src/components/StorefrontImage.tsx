"use client";

import Image, { type ImageProps } from "next/image";
import { useEffect, useMemo, useState } from "react";
import {
  lineImageCandidates,
  shouldUnoptimizeStorefrontImage,
  storefrontImageSrc,
} from "@/lib/media";

type StorefrontImageProps = Omit<ImageProps, "src"> & {
  src?: ImageProps["src"] | null;
  /** Extra sources tried in order when `src` fails to load. */
  fallbacks?: Array<string | null | undefined>;
};

/**
 * next/image wrapper for catalog/admin media.
 * Laravel `/storage` URLs skip the optimizer so a brief upload race can never
 * pin a year-long cached 404 in the shopper's browser.
 *
 * When a primary URL 404s (stale cart, replaced upload), we advance through
 * `fallbacks` instead of leaving Safari's broken-image glyph in the checkout.
 */
export function StorefrontImage({
  src,
  fallbacks,
  alt,
  unoptimized,
  onError,
  ...props
}: StorefrontImageProps) {
  const candidates = useMemo(() => {
    const primary =
      typeof src === "string" ? storefrontImageSrc(src) : null;
    if (typeof src === "string" || src == null) {
      return lineImageCandidates({
        productImage: primary,
        productImages: fallbacks,
      });
    }
    return [];
  }, [src, fallbacks]);

  const [index, setIndex] = useState(0);

  useEffect(() => {
    setIndex(0);
  }, [candidates.join("|")]);

  // Static import / Blob src from Next — keep the original Image path.
  if (typeof src !== "string" && src != null) {
    return <Image {...props} src={src} alt={alt} unoptimized={unoptimized} onError={onError} />;
  }

  const resolved = candidates[Math.min(index, Math.max(candidates.length - 1, 0))];
  if (!resolved) return null;

  const skipOptimizer =
    unoptimized ?? shouldUnoptimizeStorefrontImage(resolved);

  return (
    <Image
      {...props}
      key={resolved}
      src={resolved}
      alt={alt}
      unoptimized={skipOptimizer}
      onError={(event) => {
        if (index < candidates.length - 1) {
          setIndex((current) => current + 1);
        }
        onError?.(event);
      }}
    />
  );
}
