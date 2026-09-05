import { describe, expect, it } from "vitest";
import { toProductCardData } from "@/lib/product-card";
import { formatPrice, toSearchProduct, type Product } from "@/lib/catalog";
import { DEFAULT_CURRENCY } from "@/lib/api";

function sampleProduct(overrides: Partial<Product> = {}): Product {
  return {
    id: "p1",
    sku: "SKU-1",
    handle: "sample-bag",
    title: "Sample Bag",
    vendor: "Hermes",
    brandSlug: "hermes",
    categorySlug: "bags",
    price: "1200",
    compareAtPrice: "1500",
    available: true,
    featured: true,
    image: "/images/sample.jpg",
    images: ["/images/sample.jpg", "/images/sample-2.jpg"],
    description: "A long description that must not hydrate into cards.",
    shortDescription: "Short copy",
    material: "Leather",
    updatedAt: "2026-07-01T00:00:00.000000Z",
    colors: [
      {
        variantId: "v1",
        sku: "SKU-1-BLK",
        name: "Black",
        hex: "#000000",
        price: "1200",
        inStock: true,
        image: "/images/sample-black.jpg",
      },
    ],
    ratingAverage: 4.8,
    reviewsCount: 12,
    reviews: [
      {
        id: "r1",
        author: "Amina",
        rating: 5,
        title: "Love it",
        body: "Review body",
        recommended: true,
        createdAt: "2026-01-01",
      },
    ],
    seoTitle: "SEO",
    seoDescription: "SEO desc",
    seoKeywords: "bags",
    ...overrides,
  };
}

describe("performance: slim client payloads", () => {
  it("toSearchProduct keeps only fields needed by header search", () => {
    const slim = toSearchProduct(sampleProduct());

    expect(slim).toEqual({
      handle: "sample-bag",
      title: "Sample Bag",
      vendor: "Hermes",
      image: "/images/sample.jpg",
      price: "1200",
    });
    expect(slim).not.toHaveProperty("description");
    expect(slim).not.toHaveProperty("reviews");
    expect(slim).not.toHaveProperty("colors");
    expect(slim).not.toHaveProperty("seoTitle");
  });

  it("toProductCardData strips descriptions reviews and SEO before hydration", () => {
    const card = toProductCardData(sampleProduct());

    expect(card.handle).toBe("sample-bag");
    expect(card.compareAtPrice).toBe("1500");
    expect(card.colors).toHaveLength(1);
    expect(card).not.toHaveProperty("description");
    expect(card).not.toHaveProperty("shortDescription");
    expect(card).not.toHaveProperty("reviews");
    expect(card).not.toHaveProperty("seoTitle");
    expect(card).not.toHaveProperty("images");
    expect(JSON.stringify(card).length).toBeLessThan(
      JSON.stringify(sampleProduct()).length / 2,
    );
  });

  it("toProductDetailClientData strips reviews and SEO from PDP hydration", async () => {
    const { toProductDetailClientData } = await import("@/lib/product-detail");
    const detail = toProductDetailClientData(sampleProduct());

    expect(detail.handle).toBe("sample-bag");
    expect(detail.colors).toHaveLength(1);
    expect(detail.images).toEqual([
      "/images/sample.jpg",
      "/images/sample-2.jpg",
    ]);
    expect(detail).not.toHaveProperty("description");
    expect(detail).not.toHaveProperty("shortDescription");
    expect(detail).not.toHaveProperty("reviews");
    expect(detail).not.toHaveProperty("seoTitle");
    expect(JSON.stringify(detail).length).toBeLessThan(
      JSON.stringify(sampleProduct()).length,
    );
  });

  it("productGalleryImages keeps primary first and dedupes", async () => {
    const { productGalleryImages } = await import("@/lib/product-detail");

    expect(
      productGalleryImages({
        image: "/a.jpg",
        images: ["/a.jpg", "/b.jpg", " ", "/b.jpg", "/c.jpg"],
      }),
    ).toEqual(["/a.jpg", "/b.jpg", "/c.jpg"]);
  });

  it("mapListProduct drops reviews SEO and long copy", async () => {
    const { mapListProduct } = await import("@/lib/catalog");
    const listed = mapListProduct({
      id: "p1",
      sku: "SKU-1",
      slug: "sample-bag",
      name: "Sample Bag",
      price: 1200,
      compare_at_price: 1500,
      in_stock: true,
      featured: true,
      image: "/images/sample.jpg",
      images: ["/images/sample.jpg", "/images/sample-2.jpg"],
      description: "Long description",
      short_description: "Short",
      material: "Leather",
      updated_at: "2026-07-01T00:00:00.000000Z",
      brand: { name: "Hermes", slug: "hermes" },
      category: { slug: "bags" },
      colors: [],
      rating: { average: 4.8, count: 12 },
      reviews: [
        {
          id: "r1",
          author: "Amina",
          rating: 5,
          title: "Love it",
          body: "Review body",
          recommended: true,
          created_at: "2026-01-01",
        },
      ],
      seo: {
        title: "SEO",
        description: "SEO desc",
        keywords: "bags",
      },
    } as never);

    expect(listed.handle).toBe("sample-bag");
    expect(listed.reviews).toEqual([]);
    expect(listed.description).toBeNull();
    expect(listed.seoTitle).toBeNull();
    expect(listed.images).toEqual(["/images/sample.jpg"]);
  });
});

describe("performance: LCP hero assets", () => {
  it("uses the compressed JPEG hero base and circular brand emblem", async () => {
    const { HERO_BASE_PATH, HERO_BASE_SRC, HERO_LOGO_SRC } = await import(
      "@/lib/hero"
    );
    expect(HERO_BASE_PATH).toBe("/images/hero-layers/hero-base.jpg");
    expect(HERO_BASE_SRC).toContain("hero-base.jpg");
    expect(HERO_BASE_SRC).not.toContain("hero-base.png");
    expect(HERO_LOGO_SRC).toContain("dokan-ward-logo");
    expect(HERO_LOGO_SRC).not.toContain("wordmark.png");
  });
});

describe("performance: currency formatting stays pure", () => {
  it("formats prices without allocating heavy Intl locales by default", () => {
    expect(formatPrice(99.5, DEFAULT_CURRENCY)).toBe("LE 99.50");
    expect(formatPrice("10", { ...DEFAULT_CURRENCY, position: "after" })).toBe(
      "10.00 LE",
    );
  });
});
