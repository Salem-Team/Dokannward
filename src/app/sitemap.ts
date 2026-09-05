import type { MetadataRoute } from "next";
import {
  getAllProducts,
  getBrandHandles,
  getCollectionHandles,
  hrefForStorefrontCollection,
} from "@/lib/catalog";
import { absoluteUrl, siteOrigin, socialImage } from "@/lib/seo";

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const base = siteOrigin();
  const now = new Date();

  const staticRoutes: MetadataRoute.Sitemap = [
    { path: "", priority: 1, changeFrequency: "daily" as const },
    { path: "/brands", priority: 0.9, changeFrequency: "weekly" as const },
    { path: "/collections", priority: 0.9, changeFrequency: "weekly" as const },
    { path: "/collections/all", priority: 0.85, changeFrequency: "daily" as const },
    { path: "/pages/about", priority: 0.8, changeFrequency: "monthly" as const },
    { path: "/pages/contact", priority: 0.75, changeFrequency: "monthly" as const },
    { path: "/policies/privacy-policy", priority: 0.3, changeFrequency: "yearly" as const },
    { path: "/policies/terms-of-service", priority: 0.3, changeFrequency: "yearly" as const },
    { path: "/policies/shipping-policy", priority: 0.35, changeFrequency: "yearly" as const },
    { path: "/policies/refund-policy", priority: 0.35, changeFrequency: "yearly" as const },
  ].map((entry) => ({
    url: `${base}${entry.path}`,
    lastModified: now,
    changeFrequency: entry.changeFrequency,
    priority: entry.priority,
  }));

  let products: Awaited<ReturnType<typeof getAllProducts>> = [];
  try {
    products = await getAllProducts();
  } catch {
    products = [];
  }

  const latestProductTouch = products.reduce<Date | null>((latest, p) => {
    if (!p.updatedAt) return latest;
    const d = new Date(p.updatedAt);
    if (Number.isNaN(d.getTime())) return latest;
    return !latest || d > latest ? d : latest;
  }, null);

  let collectionRoutes: MetadataRoute.Sitemap = [];
  try {
    const handles = await getCollectionHandles();
    collectionRoutes = handles
      .filter((h) => h !== "all")
      .map((handle) => ({
        url: `${base}/collections/${handle}`,
        lastModified: latestProductTouch ?? now,
        changeFrequency: "weekly" as const,
        priority: 0.75,
      }));
  } catch {
    collectionRoutes = [];
  }

  let brandRoutes: MetadataRoute.Sitemap = [];
  try {
    const slugs = await getBrandHandles();
    brandRoutes = slugs.map((slug) => ({
      url: `${base}${hrefForStorefrontCollection({ handle: slug, kind: "brand" })}`,
      lastModified: latestProductTouch ?? now,
      changeFrequency: "weekly" as const,
      priority: 0.8,
    }));
  } catch {
    brandRoutes = [];
  }

  const productRoutes: MetadataRoute.Sitemap = products.map((p) => {
    const images = [p.image, ...(p.images || [])]
      .map((src) => socialImage(src))
      .filter((src): src is string => Boolean(src))
      .map((src) => absoluteUrl(src));
    const uniqueImages = Array.from(new Set(images)).slice(0, 8);

    return {
      url: `${base}/products/${p.handle}`,
      lastModified: p.updatedAt ? new Date(p.updatedAt) : now,
      changeFrequency: "weekly" as const,
      priority: 0.7,
      ...(uniqueImages.length ? { images: uniqueImages } : {}),
    };
  });

  return [...staticRoutes, ...collectionRoutes, ...brandRoutes, ...productRoutes];
}
