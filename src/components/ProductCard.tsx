import { formatPrice, getCurrency, type Product } from "@/lib/catalog";
import { toProductCardData } from "@/lib/product-card";
import { ProductCardClient } from "@/components/ProductCardClient";

/**
 * Server wrapper: formats price once per request (React cache) and only
 * hydrates a slim card DTO — no descriptions / reviews / SEO on the client.
 */
export async function ProductCard({
  product,
  priority = false,
}: {
  product: Product;
  priority?: boolean;
}) {
  const currency = await getCurrency();
  return (
    <ProductCardClient
      product={toProductCardData(product)}
      priority={priority}
      priceLabel={formatPrice(product.price, currency)}
      compareAtLabel={
        product.compareAtPrice
          ? formatPrice(product.compareAtPrice, currency)
          : undefined
      }
    />
  );
}
