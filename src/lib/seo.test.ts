import { describe, expect, it } from "vitest";
import { BRAND } from "@/lib/brand";
import {
  absoluteUrl,
  brandSeoFields,
  breadcrumbJsonLd,
  cleanSeoTitle,
  faqPageJsonLd,
  organizationGraph,
  pageMetadata,
  productJsonLd,
  productSeoFields,
} from "@/lib/seo";
import type { Product } from "@/lib/catalog";

const sampleProduct = {
  id: "1",
  sku: "ZT-1",
  handle: "test-tote",
  title: "Test Tote",
  vendor: "Gucci",
  brandSlug: "gucci",
  categorySlug: "tote",
  price: "450.00",
  compareAtPrice: null,
  available: true,
  featured: false,
  image: "/images/tote.jpg",
  images: ["/images/tote.jpg"],
  description: "A bag",
  shortDescription: "Short",
  material: "Leather",
  updatedAt: "2026-07-01T00:00:00.000000Z",
  colors: [
    {
      variantId: "v1",
      sku: "ZT-1-BLK",
      name: "Black",
      hex: "#111111",
      price: "450.00",
      inStock: true,
      image: "/images/tote.jpg",
    },
  ],
  ratingAverage: 4.5,
  reviewsCount: 8,
  reviews: [
    {
      id: "r1",
      author: "Sara",
      rating: 5,
      title: "Loved it",
      body: "Perfect everyday tote.",
      recommended: true,
      createdAt: "2026-06-01T00:00:00.000000Z",
    },
  ],
  seoTitle: null,
  seoDescription: "SEO desc",
  seoKeywords: null,
} satisfies Product;

const origin = BRAND.url.replace(/\/$/, "");

describe("seo helpers", () => {
  it("builds absolute urls from site origin", () => {
    expect(absoluteUrl("/products/tote")).toBe(`${origin}/products/tote`);
    expect(absoluteUrl("https://cdn.example.com/a.jpg")).toBe(
      "https://cdn.example.com/a.jpg",
    );
  });

  it("strips trailing brand from titles to avoid duplication", () => {
    expect(cleanSeoTitle(`Curated tote – ${BRAND.name}`)).toBe("Curated tote");
    expect(cleanSeoTitle("Curated tote")).toBe("Curated tote");
  });

  it("emits canonical + og + twitter for indexable pages", () => {
    const meta = pageMetadata({
      path: "/collections/bags",
      title: `Bags – ${BRAND.name}`,
      description: "Authenticated bags.",
      image: "/images/bags.jpg",
    });

    expect(meta.title).toBe("Bags");
    expect(meta.alternates).toEqual({
      canonical: `${origin}/collections/bags`,
    });
    expect(meta.openGraph?.url).toBe(`${origin}/collections/bags`);
    expect(meta.openGraph?.locale).toBe("en_EG");
    const images = meta.openGraph?.images;
    const first = Array.isArray(images) ? images[0] : images;
    expect(first).toMatchObject({
      url: `${origin}/images/bags.jpg`,
    });
    expect(meta.robots).toMatchObject({ index: true, follow: true });
  });

  it("marks utility pages as noindex", () => {
    const meta = pageMetadata({
      path: "/checkout",
      title: "Checkout",
      index: false,
    });
    expect(meta.robots).toMatchObject({ index: false, follow: false });
  });

  it("builds organization + website graph with Egypt area served", () => {
    const graph = organizationGraph({
      name: BRAND.name,
      email: "hello@example.com",
      sameAs: ["https://www.instagram.com/example"],
      currencyCode: "EGP",
    });
    const nodes = graph["@graph"] as Array<Record<string, unknown>>;
    expect(nodes.map((n) => n["@type"])).toEqual([
      "Organization",
      "WebSite",
      ["Store", "OnlineStore"],
    ]);
    const website = nodes.find((n) => n["@type"] === "WebSite")!;
    expect(website.potentialAction).toMatchObject({
      "@type": "SearchAction",
    });
    expect(website.inLanguage).toBe("en-EG");
    const org = nodes.find((n) => n["@type"] === "Organization")!;
    expect(org.areaServed).toMatchObject({ name: "Egypt" });
    const store = nodes.find((n) => Array.isArray(n["@type"]))!;
    expect(store.hasMap).toBeUndefined();
  });

  it("attaches a Google Maps pin on the store graph", () => {
    const graph = organizationGraph({
      name: BRAND.name,
      address: "The 5th Settlement, New Cairo",
      mapsUrl: "https://maps.app.goo.gl/example",
    });
    const nodes = graph["@graph"] as Array<Record<string, unknown>>;
    const store = nodes.find((n) => Array.isArray(n["@type"]))!;
    expect(store.hasMap).toBe("https://maps.app.goo.gl/example");
  });

  it("builds product offer schema with variants, material, and reviews", () => {
    const json = productJsonLd(sampleProduct, "EGP", {
      shippingFee: 10,
      returnDays: 14,
    });
    expect(json["@type"]).toBe("Product");
    expect(json.material).toBe("Leather");
    expect(json.color).toBe("Black");
    expect(Array.isArray(json.offers)).toBe(true);
    expect((json.offers as Array<Record<string, unknown>>)[0]).toMatchObject({
      "@type": "Offer",
      priceCurrency: "EGP",
      price: "450.00",
      availability: "https://schema.org/InStock",
      shippingDetails: {
        "@type": "OfferShippingDetails",
        shippingDestination: { addressCountry: "EG" },
      },
      hasMerchantReturnPolicy: {
        "@type": "MerchantReturnPolicy",
        merchantReturnDays: 14,
        applicableCountry: "EG",
      },
    });
    expect(json.aggregateRating).toMatchObject({
      ratingValue: "4.5",
      reviewCount: 8,
    });
    expect(Array.isArray(json.review)).toBe(true);
  });

  it("builds Egypt-aware product and brand SEO copy", () => {
    const product = productSeoFields({
      ...sampleProduct,
      seoDescription: null,
      shortDescription: null,
    });
    expect(product.description.toLowerCase()).toContain("egypt");
    expect(product.keywords).toContain("Gucci Egypt");

    const brand = brandSeoFields("Polène");
    expect(brand.title).toContain("Egypt");
    expect(brand.description.toLowerCase()).toContain("egypt");
  });

  it("builds breadcrumb and FAQ schemas", () => {
    const crumbs = breadcrumbJsonLd([
      { name: "Home", path: "/" },
      { name: "Bags", path: "/collections/bags" },
    ]);
    expect(crumbs?.["@type"]).toBe("BreadcrumbList");

    const faq = faqPageJsonLd([{ q: "Returns?", a: "Yes." }]);
    expect(faq?.["@type"]).toBe("FAQPage");
  });
});
