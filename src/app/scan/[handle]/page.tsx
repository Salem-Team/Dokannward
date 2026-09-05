import type { Metadata } from "next";
import { notFound } from "next/navigation";
import {
  getCheckoutSettings,
  getCurrency,
  getProductByHandle,
  getRelatedProducts,
} from "@/lib/catalog";
import { ProductScanView } from "@/components/ProductScanView";
import { JsonLd } from "@/components/JsonLd";
import {
  breadcrumbJsonLd,
  pageMetadata,
  productJsonLd,
  productSeoFields,
} from "@/lib/seo";
import { t } from "@/lib/i18n";
import { getServerLocale } from "@/lib/i18n/server";

type Props = { params: Promise<{ handle: string }> };

export const revalidate = 300;
export const dynamicParams = true;

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { handle } = await params;
  const product = await getProductByHandle(handle);
  if (!product) {
    return {
      title: "Product scan",
      robots: { index: false, follow: false },
    };
  }

  const seo = productSeoFields(product);

  return pageMetadata({
    path: `/scan/${product.handle}`,
    title: `${product.title} · Scan`,
    description:
      seo.description ||
      product.shortDescription ||
      product.description ||
      `Scan details for ${product.title} at Dokan Ward.`,
    image: product.image || product.images[0] || undefined,
    imageAlt: `${product.title} — Dokan Ward`,
    index: false,
  });
}

export default async function ProductScanPage({ params }: Props) {
  const { handle } = await params;
  const [product, checkout, currency, locale] = await Promise.all([
    getProductByHandle(handle),
    getCheckoutSettings(),
    getCurrency(),
    getServerLocale(),
  ]);

  if (!product) notFound();

  const related = await getRelatedProducts(product);
  const relatedGroups = [related.collection, related.category].filter(
    (group): group is NonNullable<typeof group> => group != null,
  );

  const crumbs = [
    { name: t(locale, "breadcrumb.home"), path: "/" },
    { name: t(locale, "breadcrumb.scan"), path: `/scan/${product.handle}` },
    { name: product.title, path: `/products/${product.handle}` },
  ];

  return (
    <>
      <JsonLd
        data={productJsonLd(product, currency.code, {
          shippingFee: checkout.standard_shipping_fee,
          returnDays: 14,
        })}
      />
      <JsonLd data={breadcrumbJsonLd(crumbs)} />
      <ProductScanView product={product} relatedGroups={relatedGroups} />
    </>
  );
}
