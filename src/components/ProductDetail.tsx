import type { Product } from "@/lib/catalog";
import { toProductDetailClientData } from "@/lib/product-detail";
import { ProductDetailClient } from "@/components/ProductDetailClient";
import { ProductReviews } from "@/components/ProductReviews";

/**
 * Server shell for the PDP — hydrates only a slim client buy box and
 * streams reviews with SSR (no client-only dynamic import).
 */
export function ProductDetail({ product }: { product: Product }) {
  return (
    <div className="container py-8 md:py-12">
      <ProductDetailClient
        product={toProductDetailClientData(product)}
        shortDescription={product.shortDescription}
        description={product.description}
      />

      <section id="reviews" className="mt-16 md:mt-24 max-w-3xl content-auto">
        <ProductReviews
          handle={product.handle}
          title={product.title}
          reviews={product.reviews}
          ratingAverage={product.ratingAverage}
          reviewsCount={product.reviewsCount}
        />
      </section>
    </div>
  );
}
