import type { Metadata } from "next";
import Link from "next/link";
import {
  formatPrice,
  getAllProducts,
  getCurrency,
  getStorefrontCategoryChips,
} from "@/lib/catalog";
import { toProductCardData } from "@/lib/product-card";
import { CollectionsProductMarquee } from "@/components/CollectionsProductMarquee";
import { PrefetchWarmup } from "@/components/PrefetchWarmup";
import { Reveal } from "@/components/Reveal";
import { JsonLd } from "@/components/JsonLd";
import { itemListJsonLd, pageMetadata } from "@/lib/seo";
import { getServerLocale } from "@/lib/i18n/server";
import { t } from "@/lib/i18n";

export const revalidate = 120;

export async function generateMetadata(): Promise<Metadata> {
  const locale = await getServerLocale();
  return pageMetadata({
    path: "/collections",
    title: t(locale, "collections.metaTitle"),
    description: t(locale, "collections.metaDesc"),
    keywords: [
      "home decor collections Egypt",
      "home decor Egypt",
      "Dokan Ward collections",
      "vases Egypt",
      "artificial plants",
      "bakhoor burners",
    ],
  });
}

export default async function CollectionsPage() {
  const locale = await getServerLocale();
  const [categories, currency, allProducts] = await Promise.all([
    getStorefrontCategoryChips(),
    getCurrency(),
    getAllProducts(),
  ]);

  const marqueeItems = allProducts.map((product) => ({
    product: toProductCardData(product),
    priceLabel: formatPrice(product.price, currency),
  }));

  const prefetchHrefs = Array.from(
    new Set([
      "/collections/all",
      ...categories.map((c) => c.href),
      ...allProducts.slice(0, 12).map((p) => `/products/${p.handle}`),
    ]),
  );

  if (categories.length === 0 && marqueeItems.length === 0) {
    const catalog = t(locale, "collections.catalog");
    const ledeEmpty = t(locale, "collections.ledeEmpty");
    const [before, after] = ledeEmpty.split(catalog);
    return (
      <div className="brand-index">
        <div className="brand-index__header container">
          <p className="brand-index__eyebrow">
            {t(locale, "collections.eyebrowEmpty")}
          </p>
          <h1 className="heading brand-index__title">
            {t(locale, "collections.title")}
          </h1>
          <p className="brand-index__lede">
            {before}
            <Link href="/collections/all" className="link-underline">
              {catalog}
            </Link>
            {after}
          </p>
        </div>
      </div>
    );
  }

  return (
    <>
      <JsonLd
        data={itemListJsonLd(
          [
            ...categories.map((c) => ({
              name: c.title,
              path: c.href,
            })),
            ...allProducts.slice(0, 24).map((p) => ({
              name: p.title,
              path: `/products/${p.handle}`,
              image: p.image || undefined,
            })),
          ],
          "Dokan Ward home décor collections in Egypt",
        )}
      />
      <PrefetchWarmup hrefs={prefetchHrefs} />

      <section className="collections-index content-auto">
        <div className="container collections-index__head">
          <Reveal>
            <p className="collections-index__eyebrow">
              {t(locale, "collections.eyebrow")}
            </p>
            <h1 className="heading collections-index__title">
              {t(locale, "collections.title")}
            </h1>
            <p className="collections-index__lede">
              {t(locale, "collections.lede")}
            </p>
          </Reveal>

          {categories.length > 0 ? (
            <Reveal delay={60}>
              <nav
                className="collections-index__chips"
                aria-label={t(locale, "collections.browseAria")}
              >
                {categories.map((c) => (
                  <Link
                    key={c.handle}
                    href={c.href}
                    prefetch
                    className="collections-index__chip"
                  >
                    {c.title}
                    <span className="collections-index__chip-count">
                      {c.products_count}
                    </span>
                  </Link>
                ))}
                <Link
                  href="/collections/all"
                  prefetch
                  className="collections-index__chip collections-index__chip--all"
                >
                  {t(locale, "collections.viewAll")}
                </Link>
              </nav>
            </Reveal>
          ) : null}
        </div>

        {marqueeItems.length > 0 ? (
          <CollectionsProductMarquee
            items={marqueeItems}
            label={t(locale, "collections.marquee")}
          />
        ) : null}
      </section>
    </>
  );
}
