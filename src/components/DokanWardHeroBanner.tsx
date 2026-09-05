import { StorefrontImage } from "@/components/StorefrontImage";
import { BRAND, withHeroCache } from "@/lib/brand";
import { IMAGE_QUALITY } from "@/lib/media";

type DokanWardHeroBannerProps = {
  className?: string;
  imageSrc?: string;
  wordmarkSrc?: string;
  alt?: string;
};

/** Drop leftover Zibra / empty admin paths so the emblem always shows. */
function resolveHeroSrc(src: string | undefined, fallback: string): string {
  const value = (src ?? "").trim();
  if (!value) return fallback;
  if (/zibra/i.test(value)) return fallback;
  return value;
}

/**
 * Landing hero: cream plate + circular Dokan Ward emblem.
 * Cinematic entrance (veil → plate → emblem → shine → ambient),
 * idle depth (ken burns, breathe, sparks), and polished hover.
 */
export function DokanWardHeroBanner({
  className = "",
  imageSrc = BRAND.heroImage,
  wordmarkSrc = BRAND.heroWordmark,
  alt = BRAND.heroAlt,
}: DokanWardHeroBannerProps) {
  const plate = withHeroCache(resolveHeroSrc(imageSrc, BRAND.heroImage));
  const emblem = withHeroCache(resolveHeroSrc(wordmarkSrc, BRAND.heroWordmark));
  const label = (alt ?? "").trim() || BRAND.heroAlt;

  return (
    <div
      className={`dokan-hero site-banner dokan-hero--cinema ${className}`.trim()}
      role="img"
      aria-label={label}
    >
      <div className="dokan-hero__frame site-banner__plate">
        <StorefrontImage
          className="dokan-hero__layer dokan-hero__exact"
          src={plate}
          alt={label}
          fill
          priority
          fetchPriority="high"
          quality={IMAGE_QUALITY.hero}
          sizes="100vw"
          draggable={false}
        />

        {/* Ambient bronze light wells */}
        <div className="dokan-hero__glow" aria-hidden="true">
          <span className="dokan-hero__glow-orb dokan-hero__glow-orb--a" />
          <span className="dokan-hero__glow-orb dokan-hero__glow-orb--b" />
          <span className="dokan-hero__glow-orb dokan-hero__glow-orb--c" />
        </div>

        {/* Opening veil — soft bronze cream */}
        <div className="dokan-hero__curtain" aria-hidden="true">
          <span className="dokan-hero__curtain-stripes dokan-hero__veil" />
          <span className="dokan-hero__curtain-ink dokan-hero__mist" />
        </div>

        <div className="dokan-hero__logo-stage dokan-hero__logo-stage--enter">
          <span className="dokan-hero__logo-aura" aria-hidden="true" />
          <span className="dokan-hero__logo-ring" aria-hidden="true" />
          <span className="dokan-hero__logo-disc" aria-hidden="true" />
          {/* eslint-disable-next-line @next/next/no-img-element -- circular emblem stays crisp outside the optimizer */}
          <img
            className="dokan-hero__logo"
            src={emblem}
            alt=""
            aria-hidden="true"
            draggable={false}
            decoding="async"
          />
          <span className="dokan-hero__logo-shine" aria-hidden="true" />
          <span className="dokan-hero__spark dokan-hero__spark--1" aria-hidden="true" />
          <span className="dokan-hero__spark dokan-hero__spark--2" aria-hidden="true" />
          <span className="dokan-hero__spark dokan-hero__spark--3" aria-hidden="true" />
        </div>

        <span className="dokan-hero__rim" aria-hidden="true" />
        <span className="dokan-hero__grain" aria-hidden="true" />
        <span className="dokan-hero__edge" aria-hidden="true" />
      </div>
    </div>
  );
}
