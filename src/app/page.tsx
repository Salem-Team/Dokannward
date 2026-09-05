import type { Metadata } from "next";
import Link from "next/link";
import { Suspense } from "react";
import { preload } from "react-dom";
import { fetchBanners, fetchTestimonials } from "@/lib/api";

import {
  getFeaturedProducts,
  getFeaturedStorefrontCategories,
  getHomepageCategoryLogos,
  getSiteContent,
  getStoreSettings,
  formatPrice,
  getCurrency,
} from "@/lib/catalog";
import { toProductCardData } from "@/lib/product-card";
import { Reveal } from "@/components/Reveal";
import { TestimonialsGrid } from "@/components/Testimonials";
import { IconArrowRight } from "@/components/Icons";
import { PrefetchWarmup } from "@/components/PrefetchWarmup";
import { DokanWardHeroBanner } from "@/components/DokanWardHeroBanner";
import { HomeBanner, resolveHomeBanners } from "@/components/HomeBanner";
import { HomeCategoryLogos } from "@/components/HomeCategoryLogos";
import { HomeCategoryShowcase } from "@/components/HomeCategoryShowcase";
import { CollectionsProductMarquee } from "@/components/CollectionsProductMarquee";
import { BRAND, withHeroCache } from "@/lib/brand";
import { resolveHomepageCategoryLogos } from "@/lib/homepageCategoryLogos";
import { PoliciesFaq } from "@/components/PoliciesFaq";
import { JsonLd } from "@/components/JsonLd";
import {
  DEFAULT_KEYWORDS,
  faqPageJsonLd,
  pageMetadata,
} from "@/lib/seo";
import { localizeCmsText } from "@/lib/i18n";
import { getServerLocale } from "@/lib/i18n/server";

export const revalidate = 180;

export async function generateMetadata(): Promise<Metadata> {
  const store = await getStoreSettings();
  const name = store.name || BRAND.name;
  const description =
    store.seo_description?.trim() ||
    store.description?.trim() ||
    BRAND.description;

  return pageMetadata({
    path: "/",
    title: `${name} — Home Decor in Egypt`,
    description,
    keywords: DEFAULT_KEYWORDS,
    absoluteTitle: true,
    image: store.seo_og_image?.trim() || BRAND.ogImage,
    siteName: name,
  });
}

/**
 * Hero + header paint immediately.
 * Catalog sections stream in behind Suspense so a slow API never leaves a white void under the header.
 * Hero base uses next/image `priority` (auto preload + AVIF/WebP); only the SVG wordmark is warmed here.
 */
export default async function HomePage() {
  const content = await getSiteContent();
  const wordmark = withHeroCache(
    content.home.hero_wordmark?.trim() || BRAND.heroWordmark,
  );
  preload(wordmark, { as: "image" });

  return (
    <div className="home-page home-page--atelier">
      <section className="w-full">
        <DokanWardHeroBanner
          className="w-full"
          imageSrc={content.home.hero_image}
          wordmarkSrc={content.home.hero_wordmark}
          alt={content.home.hero_alt}
        />
      </section>

      <Suspense fallback={null}>
        <HomeBody />
      </Suspense>
    </div>
  );
}

async function HomeBody() {
  const [
    gridProducts,
    featuredCategories,
    categoryLogos,
    banners,
    testimonials,
    content,
    locale,
    currency,
  ] = await Promise.all([
    getFeaturedProducts(),
    getFeaturedStorefrontCategories(),
    getHomepageCategoryLogos(),
    fetchBanners(),
    fetchTestimonials(),
    getSiteContent(),
    getServerLocale(),
    getCurrency(),
  ]);
  const homeBanners = resolveHomeBanners(banners);
  const homeCategoryLogos = resolveHomepageCategoryLogos(categoryLogos);
  const primaryBanner = homeBanners[0] ?? null;
  const secondaryBanner = homeBanners[1] ?? null;
  const faq = content.faq;
  const faqItems = faq.items?.length ? faq.items : [];
  const home = content.home;
  const arrivalItems = gridProducts.map((product) => ({
    product: toProductCardData(product),
    priceLabel: formatPrice(product.price, currency),
  }));

  // Keep idle prefetch lean so it never fights LCP on mid-tier phones.
  const prefetchHrefs = Array.from(
    new Set([
      "/collections",
      "/collections/all",
      ...homeCategoryLogos.slice(0, 4).map((c) => c.href),
      ...featuredCategories.slice(0, 3).map((c) => c.href),
      ...gridProducts.slice(0, 4).map((p) => `/products/${p.handle}`),
    ]),
  );

  return (
    <>
      <PrefetchWarmup hrefs={prefetchHrefs} />

      <HomeCategoryLogos logos={homeCategoryLogos} />

        <HomeCategoryShowcase categories={featuredCategories} />

        <section className="home-rail home-rail--motion">
          <div className="container">
            <Reveal variant="mask">
              <div className="product-section__header">
                <div>
                  <p className="product-section__eyebrow">
                    {localizeCmsText(
                      locale,
                      home.arrivals_eyebrow,
                      "home.arrivals.eyebrow",
                    )}
                  </p>
                  <h2 className="heading product-section__title">
                    {localizeCmsText(
                      locale,
                      home.arrivals_title,
                      "home.arrivals.title",
                    )}
                  </h2>
                </div>
                <Link href="/collections/all" className="product-section__link">
                  {localizeCmsText(
                    locale,
                    home.arrivals_link_label,
                    "home.arrivals.link",
                  )}
                  <IconArrowRight size={12} />
                </Link>
              </div>
            </Reveal>
          </div>
          {arrivalItems.length > 0 ? (
            <CollectionsProductMarquee
              items={arrivalItems}
              label={localizeCmsText(
                locale,
                home.arrivals_title,
                "home.arrivals.title",
              )}
            />
          ) : null}
        </section>

        {primaryBanner ? <HomeBanner banner={primaryBanner} /> : null}

        {secondaryBanner ? (
          <HomeBanner banner={secondaryBanner} lowercaseTitle />
        ) : null}

        {faqItems.length > 0 ? (
          <>
            <JsonLd data={faqPageJsonLd(faqItems)} />
            <PoliciesFaq
              items={faqItems}
              eyebrow={localizeCmsText(locale, faq.eyebrow, "home.faq.eyebrow")}
              title={localizeCmsText(locale, faq.title, "home.faq.title")}
            />
          </>
        ) : null}

        <TestimonialsGrid
          testimonials={testimonials}
          eyebrow={localizeCmsText(
            locale,
            home.testimonials_eyebrow,
            "home.testimonials.eyebrow",
          )}
          title={localizeCmsText(
            locale,
            home.testimonials_title,
            "home.testimonials.title",
          )}
        />
    </>
  );
}
