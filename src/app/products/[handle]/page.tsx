import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import {
  getAllProducts,
  getCheckoutSettings,
  getCurrency,
  getProductByHandle,
  getRelatedProducts,
} from "@/lib/catalog";
import { ProductDetail } from "@/components/ProductDetail";
import { ProductCard } from "@/components/ProductCard";
import { PrefetchWarmup } from "@/components/PrefetchWarmup";
import { JsonLd } from "@/components/JsonLd";
import {
  breadcrumbJsonLd,
  pageMetadata,
  productJsonLd,
  productSeoFields,
} from "@/lib/seo";
import { t } from "@/lib/i18n";
import { getServerLocale } from "@/lib/i18n/server";
import { relatedGroupTitle } from "@/lib/i18n/related";

type Props = { params: Promise<{ handle: string }> };

export const revalidate = 600;

/** Allow newly published products after ISR purge without NoFallbackError 404s. */
export const dynamicParams = true;

export async function generateStaticParams() {
  const products = await getAllProducts();
  return products.map((p) => ({ handle: p.handle }));
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { handle } = await params;
  const product = await getProductByHandle(handle);
  if (!product) notFound();

  const seo = productSeoFields(product);

  return pageMetadata({
    path: `/products/${product.handle}`,
    title: seo.title,
    description: seo.description,
    image: product.image || product.images[0] || undefined,
    imageAlt: `${product.title}${product.vendor ? ` by ${product.vendor}` : ""} — Dokan Ward Egypt`,
    keywords: seo.keywords,
  });
}

export default async function ProductPage({ params }: Props) {
  const { handle } = await params;
  const [product, checkout, currency, locale] = await Promise.all([
    getProductByHandle(handle),
    getCheckoutSettings(),
    getCurrency(),
    getServerLocale(),
  ]);
  if (!product) notFound();

  const related = await getRelatedProducts(product);
  const primaryCollection = product.collections?.[0] ?? null;
  const relatedGroups = [related.collection, related.category].filter(
    (group): group is NonNullable<typeof group> => group != null,
  );

  const crumbs = [
    { name: t(locale, "breadcrumb.home"), path: "/" },
    { name: t(locale, "breadcrumb.collections"), path: "/collections" },
  ];
  if (primaryCollection) {
    crumbs.push({
      name: primaryCollection.name,
      path: `/collections/${primaryCollection.slug}`,
    });
  } else if (product.categorySlug) {
    crumbs.push({
      name: product.categoryName || t(locale, "breadcrumb.category"),
      path: `/collections/${product.categorySlug}`,
    });
  } else if (product.brandSlug) {
    crumbs.push({
      name: product.vendor || t(locale, "breadcrumb.brand"),
      path: `/brands/${product.brandSlug}`,
    });
  }
  crumbs.push({
    name: product.title,
    path: `/products/${product.handle}`,
  });

  return (
    <>
      <JsonLd
        data={productJsonLd(product, currency.code, {
          shippingFee: checkout.standard_shipping_fee,
          returnDays: 14,
        })}
      />
      <JsonLd data={breadcrumbJsonLd(crumbs)} />
      <PrefetchWarmup
        hrefs={[
          "/collections/all",
          ...relatedGroups.flatMap((group) =>
            group.products.map((p) => `/products/${p.handle}`),
          ),
        ]}
      />
      <ProductDetail product={product} />

      {relatedGroups.length > 0 ? (
        <div className="container mt-16 md:mt-24 mb-16 md:mb-24 space-y-16 content-auto">
          {relatedGroups.map((group) => {
            const title = relatedGroupTitle(locale, group);
            return (
              <section key={`${group.kind}-${group.href}`} aria-label={title}>
                <div className="flex items-end justify-between gap-4 mb-8">
                  <h2 className="heading text-2xl md:text-3xl">{title}</h2>
                  {group.href ? (
                    <Link
                      href={group.href}
                      className="product-related__all text-xs tracking-[0.12em] link-underline opacity-60 hover:opacity-100"
                    >
                      {t(locale, "collections.viewAll")}
                    </Link>
                  ) : null}
                </div>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
                  {group.products.map((p) => (
                    <ProductCard key={p.handle} product={p} />
                  ))}
                </div>
              </section>
            );
          })}
        </div>
      ) : null}
    </>
  );
}
