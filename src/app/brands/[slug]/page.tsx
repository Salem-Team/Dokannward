import type { Metadata } from "next";
import type { ReactNode } from "react";
import Link from "next/link";
import { StorefrontImage } from "@/components/StorefrontImage";
import { notFound } from "next/navigation";
import {
  getBrandHandles,
  getCollectionMeta,
  getCollectionProductList,
  hrefForStorefrontCollection,
} from "@/lib/catalog";
import { IMAGE_QUALITY } from "@/lib/media";
import { ProductCard } from "@/components/ProductCard";
import { PrefetchWarmup } from "@/components/PrefetchWarmup";
import { Reveal } from "@/components/Reveal";
import { IconArrowRight } from "@/components/Icons";
import { JsonLd } from "@/components/JsonLd";
import {
  brandSeoFields,
  breadcrumbJsonLd,
  collectionJsonLd,
  pageMetadata,
  socialImage,
} from "@/lib/seo";
import { getServerLocale } from "@/lib/i18n/server";
import { t, type Locale } from "@/lib/i18n";

type Props = { params: Promise<{ slug: string }> };

export const revalidate = 600;

/** Allow newly added admin brands before the next static rebuild. */
export const dynamicParams = true;

export async function generateStaticParams() {
  const slugs = await getBrandHandles();
  return slugs.map((slug) => ({ slug }));
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { slug } = await params;
  const brand = await getCollectionMeta(slug);
  if (!brand || brand.kind !== "brand") notFound();
  const name = brand.display_title || brand.title;
  const seo = brandSeoFields(name, brand.description);
  return pageMetadata({
    path: hrefForStorefrontCollection(brand),
    title: seo.title,
    description: seo.description,
    image: socialImage(brand.image),
    imageAlt: `${name} at Dokan Ward Egypt`,
    keywords: seo.keywords,
  });
}

function BrandShell({
  title,
  count,
  image,
  children,
  prefetchHrefs,
  locale,
}: {
  title: string;
  count: number;
  image?: string | null;
  children: ReactNode;
  prefetchHrefs: string[];
  locale: Locale;
}) {
  const piecesLabel =
    count === 1
      ? t(locale, "home.category.piece", { count })
      : t(locale, "home.category.pieces", { count });

  return (
    <div className="collection-room">
      <PrefetchWarmup hrefs={prefetchHrefs} />

      <header className="collection-room__header container">
        <Reveal>
          <Link href="/brands" className="collection-room__back">
            <span className="collection-room__back-arrow" aria-hidden="true">
              ←
            </span>
            {t(locale, "brands.title")}
          </Link>
        </Reveal>

        <div className="collection-room__heading">
          <div className="collection-room__intro">
            <div className="collection-room__identity">
              {image ? (
                <Reveal>
                  <div className="collection-room__mark">
                    <StorefrontImage
                      src={image}
                      alt={`${title} logo`}
                      width={160}
                      height={160}
                      priority
                      quality={IMAGE_QUALITY.hero}
                      sizes="(min-width: 768px) 80px, 64px"
                      className="collection-room__mark-image"
                    />
                  </div>
                </Reveal>
              ) : null}
              <div className="collection-room__identity-copy">
                <Reveal variant="mask">
                  <h1 className="heading collection-room__title">{title}</h1>
                </Reveal>
              </div>
            </div>
          </div>
          <Reveal delay={100}>
            <p className="collection-room__meta">
              <span className="collection-room__meta-line" aria-hidden="true" />
              <span>{piecesLabel}</span>
            </p>
          </Reveal>
        </div>
      </header>

      <div className="collection-room__body container">{children}</div>
    </div>
  );
}

export default async function BrandPage({ params }: Props) {
  const { slug } = await params;
  const locale = await getServerLocale();
  const [brand, items] = await Promise.all([
    getCollectionMeta(slug),
    getCollectionProductList(slug),
  ]);

  if (!brand || brand.kind !== "brand") notFound();

  const title = brand.display_title || brand.title;
  const path = hrefForStorefrontCollection(brand);

  return (
    <>
      <JsonLd
        data={collectionJsonLd(
          brand,
          path,
          items.map((p) => ({
            title: p.title,
            handle: p.handle,
            image: p.image,
          })),
        )}
      />
      <JsonLd
        data={breadcrumbJsonLd([
          { name: t(locale, "nav.home"), path: "/" },
          { name: t(locale, "brands.title"), path: "/brands" },
          { name: title, path },
        ])}
      />
      <BrandShell
        title={title}
        count={items.length}
        image={brand.image}
        locale={locale}
        prefetchHrefs={items.slice(0, 12).map((p) => `/products/${p.handle}`)}
      >
        {items.length === 0 ? (
          <EmptyBrand />
        ) : (
          <div className="collection-room__grid">
            {items.map((p, i) => (
              <div key={p.handle} className="collection-room__cell content-auto">
                <ProductCard product={p} priority={i < 2} />
              </div>
            ))}
          </div>
        )}
      </BrandShell>
    </>
  );
}

async function EmptyBrand() {
  const locale = await getServerLocale();
  return (
    <div className="collection-room__empty">
      <Reveal>
        <p className="collection-room__empty-eyebrow">
          {t(locale, "collections.comingSoon")}
        </p>
        <h2 className="heading collection-room__empty-title">
          {t(locale, "collections.emptyTitle")}
        </h2>
        <p className="collection-room__empty-text">
          {t(locale, "brands.emptyText")}
        </p>
        <Link href="/brands" className="btn-luxury mt-8 inline-flex">
          <span>{t(locale, "collections.browseBrands")}</span>
          <IconArrowRight size={12} />
        </Link>
      </Reveal>
    </div>
  );
}
