import type { Metadata } from "next";
import Link from "next/link";
import { StorefrontImage } from "@/components/StorefrontImage";
import { IMAGE_QUALITY } from "@/lib/media";
import { notFound, permanentRedirect } from "next/navigation";
import {
  getCollectionHandles,
  getCollectionMeta,
  getCollectionProductList,
  getParentCollection,
  hrefForStorefrontCollection,
  type Product,
  type StorefrontCollection,
} from "@/lib/catalog";
import { ProductCard } from "@/components/ProductCard";
import { PrefetchWarmup } from "@/components/PrefetchWarmup";
import { Reveal } from "@/components/Reveal";
import { IconArrowRight } from "@/components/Icons";
import { JsonLd } from "@/components/JsonLd";
import {
  brandSeoFields,
  breadcrumbJsonLd,
  collectionJsonLd,
  collectionSeoFields,
  pageMetadata,
  socialImage,
} from "@/lib/seo";
import { getServerLocale } from "@/lib/i18n/server";
import { t, type Locale } from "@/lib/i18n";

type Props = { params: Promise<{ handle: string }> };

export const revalidate = 600;

/** Allow newly added admin collections/categories after ISR purge. */
export const dynamicParams = true;

export async function generateStaticParams() {
  const handles = await getCollectionHandles();
  return handles.map((handle) => ({ handle }));
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { handle } = await params;
  if (handle === "all") {
    const seo = collectionSeoFields(
      "Catalog",
      "The full Dokan Ward home décor edit in Egypt — plants, vases, bakhoor, candles, and boho pieces.",
      "catalog",
    );
    return pageMetadata({
      path: "/collections/all",
      title: seo.title,
      description: seo.description,
      keywords: seo.keywords,
    });
  }
  const c = await getCollectionMeta(handle);
  if (!c) notFound();
  if (c.kind === "brand") {
    const name = c.display_title || c.title;
    const seo = brandSeoFields(name, c.description);
    return pageMetadata({
      path: hrefForStorefrontCollection(c),
      title: seo.title,
      description: seo.description,
      image: socialImage(c.image),
      imageAlt: `${name} at Dokan Ward Egypt`,
      keywords: seo.keywords,
    });
  }
  const name = c.display_title || c.title;
  const seo = collectionSeoFields(name, c.description, c.kind);
  return pageMetadata({
    path: `/collections/${c.handle}`,
    title: seo.title,
    description: seo.description,
    image: socialImage(c.image),
    imageAlt: `${name} collection — Dokan Ward Egypt`,
    keywords: seo.keywords,
  });
}

export default async function CollectionPage({ params }: Props) {
  const { handle } = await params;
  const locale = await getServerLocale();
  const collectionsLabel = t(locale, "collections.title");
  const catalogTitle = t(locale, "collections.catalogTitle");
  const catalogLede = t(locale, "collections.catalogLede");
  const homeLabel = t(locale, "nav.home");

  if (handle === "all") {
    const items = await getCollectionProductList("all");
    const catalogMeta: StorefrontCollection = {
      handle: "all",
      title: catalogTitle,
      display_title: catalogTitle,
      image: null,
      kind: "catalog",
      products_count: items.length,
      description: catalogLede,
    };
    return (
      <>
        <JsonLd
          data={collectionJsonLd(
            catalogMeta,
            "/collections/all",
            items.map((p) => ({
              title: p.title,
              handle: p.handle,
              image: p.image,
            })),
          )}
        />
        <JsonLd
          data={breadcrumbJsonLd([
            { name: homeLabel, path: "/" },
            { name: collectionsLabel, path: "/collections" },
            { name: catalogTitle, path: "/collections/all" },
          ])}
        />
        <CollectionExperience
          title={catalogTitle}
          count={items.length}
          description={catalogLede}
          products={items}
          backHref="/collections"
          backLabel={collectionsLabel}
          locale={locale}
        />
      </>
    );
  }

  const [collection, items, parent] = await Promise.all([
    getCollectionMeta(handle),
    getCollectionProductList(handle),
    getParentCollection(handle),
  ]);
  if (!collection) notFound();

  if (collection.kind === "brand") {
    permanentRedirect(hrefForStorefrontCollection(collection));
  }

  const title = collection.display_title || collection.title;
  const path = `/collections/${collection.handle}`;
  const crumbs = [
    { name: homeLabel, path: "/" },
    { name: collectionsLabel, path: "/collections" },
  ];
  if (collection.kind === "category" && parent) {
    crumbs.push({
      name: parent.display_title || parent.title,
      path: hrefForStorefrontCollection(parent),
    });
  }
  crumbs.push({ name: title, path });

  return (
    <>
      <JsonLd
        data={collectionJsonLd(
          collection,
          path,
          items.map((p) => ({
            title: p.title,
            handle: p.handle,
            image: p.image,
          })),
        )}
      />
      <JsonLd data={breadcrumbJsonLd(crumbs)} />
      <CollectionExperience
        title={title}
        count={items.length}
        image={collection.image || parent?.image}
        description={collection.description}
        backHref="/collections"
        backLabel={collectionsLabel}
        products={items}
        locale={locale}
      />
    </>
  );
}

/** Products-only collection room — header + grid, no category/brand galleries. */
function CollectionExperience({
  title,
  count,
  image,
  description,
  backHref = "/collections",
  backLabel,
  products,
  locale,
}: {
  title: string;
  count: number;
  image?: string | null;
  description?: string | null;
  backHref?: string;
  backLabel: string;
  products: Product[];
  locale: Locale;
}) {
  const prefetch = products.slice(0, 12).map((p) => `/products/${p.handle}`);
  const piecesLabel =
    count === 1
      ? t(locale, "home.category.piece", { count })
      : t(locale, "home.category.pieces", { count });

  return (
    <div className="collection-room">
      <PrefetchWarmup hrefs={prefetch} />

      <header className="collection-room__header container">
        <Reveal>
          <Link href={backHref} className="collection-room__back">
            <span className="collection-room__back-arrow" aria-hidden="true">
              ←
            </span>
            {backLabel}
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
                      alt=""
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
                {description ? (
                  <Reveal delay={80}>
                    <p className="collection-room__lede">{description}</p>
                  </Reveal>
                ) : null}
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

      <div className="collection-room__body container">
        {products.length === 0 ? (
          <EmptyCollection />
        ) : (
          <div className="collection-room__grid">
            {products.map((p, i) => (
              <div key={p.handle} className="collection-room__cell content-auto">
                <ProductCard product={p} priority={i < 2} />
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

async function EmptyCollection() {
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
          {t(locale, "collections.emptyText")}
        </p>
        <Link href="/collections" className="btn-luxury mt-8 inline-flex">
          <span>{t(locale, "collections.browseCollections")}</span>
          <IconArrowRight size={12} />
        </Link>
      </Reveal>
    </div>
  );
}
