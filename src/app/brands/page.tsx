import type { Metadata } from "next";
import Link from "next/link";
import { StorefrontImage } from "@/components/StorefrontImage";
import {
  getCollectionCounts,
  getStorefrontBrands,
  hrefForStorefrontCollection,
} from "@/lib/catalog";
import { IMAGE_QUALITY } from "@/lib/media";
import { PrefetchWarmup } from "@/components/PrefetchWarmup";
import { Reveal } from "@/components/Reveal";
import { IconArrowRight } from "@/components/Icons";
import { JsonLd } from "@/components/JsonLd";
import { itemListJsonLd, pageMetadata } from "@/lib/seo";
import { getServerLocale } from "@/lib/i18n/server";
import { t } from "@/lib/i18n";

export const revalidate = 600;

export async function generateMetadata(): Promise<Metadata> {
  const locale = await getServerLocale();
  return pageMetadata({
    path: "/brands",
    title: t(locale, "brands.metaTitle"),
    description: t(locale, "brands.metaDesc"),
    keywords: [
      "home collections Egypt",
      "Dokan Ward shop",
      "Dokan Ward brands",
      "home décor pieces",
    ],
  });
}

export default async function BrandsPage() {
  const locale = await getServerLocale();
  const [brands, counts] = await Promise.all([
    getStorefrontBrands(),
    getCollectionCounts(),
  ]);
  const prefetchHrefs = brands.map((b) => hrefForStorefrontCollection(b));
  const total = brands.length;
  const countLabel =
    total === 1
      ? t(locale, "brands.collectionCount", { count: total })
      : t(locale, "brands.collectionsCount", { count: total });

  return (
    <div className="brand-index">
      <JsonLd
        data={itemListJsonLd(
          brands.map((b) => ({
            name: b.display_title || b.title,
            path: hrefForStorefrontCollection(b),
            image: b.image,
          })),
          "Dokan Ward home décor in Egypt",
        )}
      />
      <PrefetchWarmup hrefs={prefetchHrefs} />

      <header className="brand-index__header container">
        <Reveal>
          <p className="brand-index__eyebrow">{t(locale, "brands.eyebrow")}</p>
        </Reveal>
        <Reveal variant="mask">
          <h1 className="heading brand-index__title">Dokan Ward</h1>
        </Reveal>
        <Reveal delay={120}>
          <p className="brand-index__lede">{t(locale, "brands.lede")}</p>
          <p className="brand-index__count">{countLabel}</p>
        </Reveal>
      </header>

      <div className="brand-index__grid container">
        {brands.map((b, i) => {
          const productsCount = counts[b.handle] ?? b.products_count;
          const label = b.display_title || b.title;
          const delay = Math.min(i * 55, 440);
          const aria =
            productsCount === 1
              ? t(locale, "brands.productAria", {
                  label,
                  count: productsCount,
                })
              : t(locale, "brands.productsAria", {
                  label,
                  count: productsCount,
                });

          return (
            <Reveal key={b.handle} delay={delay} className="brand-tile-reveal">
              <Link
                href={hrefForStorefrontCollection(b)}
                prefetch
                className="brand-tile brand-tile--house"
                aria-label={aria}
              >
                <div
                  className={`brand-tile__frame${b.image ? "" : " brand-tile__frame--typo"}`}
                >
                  {b.image ? (
                    <div className="brand-tile__stage">
                      <StorefrontImage
                        src={b.image}
                        alt={label}
                        fill
                        sizes="(max-width: 768px) 50vw, (max-width: 1200px) 33vw, 25vw"
                        quality={IMAGE_QUALITY.hero}
                        priority={i < 2}
                        className="brand-tile__image"
                      />
                    </div>
                  ) : (
                    <span className="brand-tile__wordmark" aria-hidden="true">
                      {label}
                    </span>
                  )}

                  <div className="brand-tile__veil" aria-hidden="true" />

                  <span className="brand-tile__cta">
                    <span className="brand-tile__cta-fill" aria-hidden="true" />
                    <span className="brand-tile__cta-label">
                      {t(locale, "brands.view")}
                      <IconArrowRight size={11} />
                    </span>
                  </span>
                </div>

                <div className="brand-tile__meta">
                  <h2 className="brand-tile__name">{label}</h2>
                  <p className="brand-tile__count">
                    {productsCount === 1
                      ? t(locale, "brands.productCount", {
                          count: productsCount,
                        })
                      : t(locale, "brands.productsCount", {
                          count: productsCount,
                        })}
                  </p>
                </div>
              </Link>
            </Reveal>
          );
        })}
      </div>
    </div>
  );
}
