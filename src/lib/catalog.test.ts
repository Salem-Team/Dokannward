import { describe, expect, it } from "vitest";
import {
  formatPrice,
  mapListProduct,
  mapProduct,
  prioritizeRelatedProducts,
  type Product,
} from "@/lib/catalog";
import type { ApiProduct } from "@/lib/api";
import { lineKey } from "@/context/cart";

const sampleApiProduct: ApiProduct = {
  id: "prod-1",
  sku: "ZT-100",
  slug: "classic-tote",
  name: "Classic Tote",
  short_description: "Everyday luxury",
  description: "Full description",
  price: "450.00",
  compare_at_price: "500.00",
  material: "Leather",
  featured: true,
  in_stock: true,
  brand: { id: "b1", name: "Dior", slug: "dior", logo_url: null },
  category: { id: "c1", name: "Bags", slug: "bags" },
  collections: [
    { id: "col-1", name: "Summer Edit", slug: "summer-edit" },
  ],
  image: "/images/tote.jpg",
  images: ["/images/tote.jpg", "/images/tote-2.jpg"],
  colors: [
    {
      variant_id: "v1",
      sku: "ZT-100-NOIR",
      name: "Noir",
      hex: "#111111",
      price: 460,
      in_stock: true,
      image: "/images/tote-noir.jpg",
    },
  ],
  rating: { average: 4.5, count: 12 },
  reviews: [
    {
      id: "r1",
      author: "Maya",
      rating: 5,
      title: "Perfect",
      body: "Loved it",
      recommended: true,
      created_at: "2026-01-01T00:00:00Z",
    },
  ],
  seo: {
    title: "Classic Tote | Dokan Ward",
    description: "Shop the Classic Tote",
    keywords: "tote,bags",
  },
};

describe("mapProduct", () => {
  it("maps admin API payloads into storefront product shape", () => {
    const product = mapProduct(sampleApiProduct);

    expect(product).toMatchObject({
      id: "prod-1",
      handle: "classic-tote",
      title: "Classic Tote",
      vendor: "Dior",
      brandSlug: "dior",
      categorySlug: "bags",
      categoryName: "Bags",
      collections: [
        { id: "col-1", name: "Summer Edit", slug: "summer-edit" },
      ],
      price: "450.00",
      compareAtPrice: "500.00",
      available: true,
      featured: true,
      ratingAverage: 4.5,
      reviewsCount: 12,
      seoTitle: "Classic Tote | Dokan Ward",
    });

    expect(product.sizes).toEqual([]);
    expect(product.colors).toEqual([
      {
        variantId: "v1",
        sku: "ZT-100-NOIR",
        name: "Noir",
        hex: "#111111",
        price: "460",
        compareAtPrice: null,
        inStock: true,
        stock: null,
        image: "/images/tote-noir.jpg",
        images: ["/images/tote-noir.jpg"],
        sizes: [],
      },
    ]);

    expect(product.reviews[0]).toMatchObject({
      id: "r1",
      author: "Maya",
      createdAt: "2026-01-01T00:00:00Z",
    });
  });

  it("falls back gracefully when optional relations are missing", () => {
    const product = mapProduct({
      ...sampleApiProduct,
      brand: null,
      category: null,
      compare_at_price: null,
      colors: [],
      rating: undefined,
      reviews: undefined,
      seo: null,
    });

    expect(product.vendor).toBe("Dokan Ward");
    expect(product.brandSlug).toBeNull();
    expect(product.categorySlug).toBeNull();
    expect(product.compareAtPrice).toBeNull();
    expect(product.colors).toEqual([]);
    expect(product.ratingAverage).toBeNull();
    expect(product.reviewsCount).toBe(0);
    expect(product.reviews).toEqual([]);
  });

  it("defaults missing color hex to a neutral swatch", () => {
    const product = mapProduct({
      ...sampleApiProduct,
      colors: [
        {
          ...sampleApiProduct.colors[0],
          hex: null,
        },
      ],
    });

    expect(product.colors[0].hex).toBe("#cccccc");
  });
});

describe("mapListProduct", () => {
  it("uses the admin main image on listing cards when set", () => {
    const product = mapListProduct(sampleApiProduct);

    expect(product.image).toBe("/images/tote.jpg");
    expect(product.images).toEqual(["/images/tote.jpg"]);
  });

  it("falls back to the first color photo when no main image exists", () => {
    const product = mapListProduct({
      ...sampleApiProduct,
      image: null,
      images: [],
    });

    expect(product.image).toBe("/images/tote-noir.jpg");
    expect(product.images).toEqual(["/images/tote-noir.jpg"]);
  });

  it("falls back to gallery cover when no color photos exist", () => {
    const product = mapListProduct({
      ...sampleApiProduct,
      colors: [
        {
          ...sampleApiProduct.colors[0],
          image: null,
          images: [],
        },
      ],
    });

    expect(product.image).toBe("/images/tote.jpg");
  });
});

describe("prioritizeRelatedProducts", () => {
  it("shows collection products first and removes them from the category rail", () => {
    const product = mapProduct(sampleApiProduct);
    const collectionOnly = {
      ...sampleApiProduct,
      id: "prod-2",
      slug: "summer-shoe",
      name: "Summer Shoe",
    };
    const categoryOnly = {
      ...sampleApiProduct,
      id: "prod-3",
      slug: "category-bag",
      name: "Category Bag",
      collections: [],
    };

    const groups = prioritizeRelatedProducts(
      product,
      [sampleApiProduct, collectionOnly],
      [collectionOnly, categoryOnly],
    );

    expect(groups.collection?.kind).toBe("collection");
    expect(groups.collection?.name).toBe("Summer Edit");
    expect(groups.collection?.products.map((p) => p.handle)).toEqual([
      "summer-shoe",
    ]);
    expect(groups.category?.kind).toBe("category");
    expect(groups.category?.name).toBe("Bags");
    expect(groups.category?.products.map((p) => p.handle)).toEqual([
      "category-bag",
    ]);
  });
});

describe("formatPrice", () => {
  it("formats with the symbol before the amount by default", () => {
    expect(formatPrice("1200")).toBe("LE 1200.00");
    expect(formatPrice(99.5, { code: "EGP", symbol: "LE", position: "before" })).toBe(
      "LE 99.50",
    );
  });

  it("supports after-position currencies", () => {
    expect(formatPrice(40, { code: "EUR", symbol: "€", position: "after" })).toBe("40.00 €");
  });
});

describe("cart lineKey", () => {
  const product = mapProduct(sampleApiProduct) as Product;

  it("keys plain lines by product handle", () => {
    expect(lineKey(product.handle)).toBe("classic-tote");
  });

  it("keys colored lines by handle + variant id", () => {
    expect(lineKey(product.handle, product.colors[0])).toBe("classic-tote::v1");
  });
});
