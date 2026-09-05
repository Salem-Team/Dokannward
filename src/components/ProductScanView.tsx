import Link from "next/link";
import type { Product, RelatedProductGroup } from "@/lib/catalog";
import { ProductDetail } from "@/components/ProductDetail";
import { ProductCard } from "@/components/ProductCard";
import { PrefetchWarmup } from "@/components/PrefetchWarmup";
import { BRAND } from "@/lib/brand";
import { getServerLocale } from "@/lib/i18n/server";
import { t } from "@/lib/i18n";
import { relatedGroupTitle } from "@/lib/i18n/related";

/**
 * Full storefront PDP after a QR scan — same gallery, variants, cart, and
 * reviews as /products/[handle], with a light authenticated scan strip.
 */
export async function ProductScanView({
  product,
  relatedGroups,
}: {
  product: Product;
  relatedGroups: RelatedProductGroup[];
}) {
  const locale = await getServerLocale();
  return (
    <main className="product-scan-flow">
      <div className="product-scan-banner" role="status">
        <div className="product-scan-banner__stripes" aria-hidden="true">
          <span />
          <span />
          <span />
          <span />
          <span />
        </div>
        <div className="product-scan-banner__inner container">
          <p className="product-scan-banner__copy">
            <span className="product-scan-banner__seal">
              {t(locale, "product.authenticated")}
            </span>
            <span>
              {t(locale, "scan.verified", { brand: BRAND.name })}
            </span>
          </p>
          <Link
            href={`/products/${product.handle}`}
            className="product-scan-banner__link"
          >
            {t(locale, "scan.openProduct")}
          </Link>
        </div>
      </div>

      <PrefetchWarmup
        hrefs={[
          "/collections/all",
          `/products/${product.handle}`,
          ...relatedGroups.flatMap((group) =>
            group.products.map((item) => `/products/${item.handle}`),
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
                  {group.products.map((item) => (
                    <ProductCard key={item.handle} product={item} />
                  ))}
                </div>
              </section>
            );
          })}
        </div>
      ) : null}
    </main>
  );
}
