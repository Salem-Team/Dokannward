import { StorefrontImage } from "@/components/StorefrontImage";
import Link from "next/link";
import type { StorefrontBanner } from "@/lib/api";
import { Reveal } from "@/components/Reveal";
import { getServerLocale } from "@/lib/i18n/server";
import { localizeCmsText } from "@/lib/i18n";

/** Homepage renders live CTA plates only — never stock/mock lifestyle fallbacks. */
export function resolveHomeBanners(live: StorefrontBanner[]): StorefrontBanner[] {
  const seen = new Set<string>();
  const unique: StorefrontBanner[] = [];
  for (const banner of live) {
    const image = banner.image_url?.trim() || "";
    if (!image) continue;
    if (image.includes("handbags-and-accessories-natural")) continue;
    const key = banner.id || image;
    if (seen.has(key)) continue;
    seen.add(key);
    unique.push(banner);
  }
  return unique.slice(0, 2);
}

export async function HomeBanner({
  banner,
  lowercaseTitle = false,
}: {
  banner: StorefrontBanner;
  lowercaseTitle?: boolean;
}) {
  const locale = await getServerLocale();
  const href = banner.button_url?.trim() || "/collections/all";
  const image = banner.image_url?.trim();
  if (!image) return null;
  const label = localizeCmsText(
    locale,
    banner.button_text?.trim() || "",
    "banner.shopNow",
  );

  return (
    <section className="site-banner site-banner--motion content-auto">
      <Reveal className="site-banner__reveal">
        <div className="site-banner__plate">
          <StorefrontImage
            src={image}
            alt=""
            width={1920}
            height={1280}
            loading="lazy"
            className="site-banner__media media-mono"
            sizes="100vw"
          />
          <div className="site-banner__veil" aria-hidden="true" />
          <span className="site-banner__stripe" aria-hidden="true" />
          <div className="site-banner__content">
            <div className="relative container py-12 md:py-16 text-white max-w-xl">
              {banner.title && (
                <Reveal variant="mask">
                  <h2
                    className={`editorial-title text-4xl md:text-6xl mb-4${
                      lowercaseTitle ? " heading lowercase" : ""
                    }`}
                  >
                    {banner.title}
                  </h2>
                </Reveal>
              )}
              {banner.subtitle ? (
                <Reveal delay={140}>
                  <p className="text-base md:text-lg opacity-90 mb-8 max-w-md">
                    {banner.subtitle}
                  </p>
                </Reveal>
              ) : null}
              <Reveal delay={banner.subtitle ? 220 : 140}>
                <Link href={href} className="btn-on-dark">
                  <span>{label}</span>
                </Link>
              </Reveal>
            </div>
          </div>
        </div>
      </Reveal>
    </section>
  );
}
