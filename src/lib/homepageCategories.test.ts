import { describe, expect, it } from "vitest";
import type { ApiCategory } from "@/lib/api";
import {
  isSyntheticCollectionRoot,
  pickFeaturedStorefrontCategories,
  pickHomepageCategoryLogos,
  pickStorefrontCategoryChips,
} from "@/lib/homepageCategories";

function cat(
  partial: Partial<ApiCategory> &
    Pick<ApiCategory, "id" | "name" | "slug">,
): ApiCategory {
  return {
    position: 0,
    is_featured: true,
    logo_url: null,
    image: null,
    collection_id: null,
    ...partial,
  };
}

describe("isSyntheticCollectionRoot", () => {
  it("detects API collection wrappers where id === collection_id", () => {
    expect(
      isSyntheticCollectionRoot({ id: "bags-root", collection_id: "bags-root" }),
    ).toBe(true);
  });

  it("never treats a real category as a collection root", () => {
    expect(
      isSyntheticCollectionRoot({
        id: "cat-1",
        collection_id: "bags-root",
      }),
    ).toBe(false);
    expect(
      isSyntheticCollectionRoot({ id: "orphan", collection_id: null }),
    ).toBe(false);
  });
});

describe("pickHomepageCategoryLogos", () => {
  const tree: ApiCategory[] = [
    cat({
      id: "bags-root",
      name: "Bags",
      slug: "bags",
      collection_id: "bags-root",
      logo_url: null,
    }),
    cat({
      id: "bags-leaf",
      name: "Bags",
      slug: "bags",
      collection_id: "bags-root",
      logo_url: "https://cdn.example/bags.jpg",
      is_featured: true,
      position: 0,
    }),
    cat({
      id: "shoes",
      name: "Shoes",
      slug: "shoes",
      logo_url: "https://cdn.example/shoes.jpg",
      is_featured: true,
      position: 1,
    }),
    cat({
      id: "homewear",
      name: "Homewear",
      slug: "homewear",
      logo_url: "https://cdn.example/homewear.jpg",
      // Regression: Featured off must NOT hide the logo mark.
      is_featured: false,
      position: 2,
    }),
    cat({
      id: "sunglasses",
      name: "Sunglasses",
      slug: "sunglasses",
      logo_url: "https://cdn.example/sunglasses.jpg",
      is_featured: true,
      position: 3,
    }),
    cat({
      id: "accessories",
      name: "Accessories",
      slug: "accessories",
      logo_url: "https://cdn.example/accessories.jpg",
      is_featured: true,
      position: 4,
    }),
    cat({
      id: "no-logo",
      name: "Empty Mark",
      slug: "empty-mark",
      logo_url: "  ",
      is_featured: true,
    }),
  ];

  it("includes every category with a logo, including non-featured Homewear", () => {
    const logos = pickHomepageCategoryLogos(tree);
    expect(logos.map((l) => l.handle)).toEqual([
      "bags",
      "shoes",
      "sunglasses",
      "accessories",
      "homewear",
    ]);
    expect(logos).toHaveLength(5);
  });

  it("never invents logos and skips synthetic collection roots", () => {
    const logos = pickHomepageCategoryLogos(tree);
    expect(logos.every((l) => l.logo.startsWith("https://"))).toBe(true);
    expect(logos.find((l) => l.handle === "empty-mark")).toBeUndefined();
  });

  it("sorts Featured ahead of non-featured, then by position", () => {
    const logos = pickHomepageCategoryLogos(tree);
    expect(logos.map((l) => l.handle).slice(0, 4)).toEqual([
      "bags",
      "shoes",
      "sunglasses",
      "accessories",
    ]);
    expect(logos.at(-1)?.handle).toBe("homewear");
  });

  it("dedupes by slug so collection+leaf collisions do not double the rail", () => {
    const dup: ApiCategory[] = [
      cat({
        id: "a1",
        name: "A",
        slug: "dup",
        logo_url: "https://cdn.example/a.jpg",
      }),
      cat({
        id: "a2",
        name: "A again",
        slug: "dup",
        logo_url: "https://cdn.example/b.jpg",
      }),
    ];
    expect(pickHomepageCategoryLogos(dup)).toHaveLength(1);
    expect(pickHomepageCategoryLogos(dup)[0].logo).toContain("a.jpg");
  });
});

describe("pickFeaturedStorefrontCategories", () => {
  it("only keeps Featured categories (Homewear off stays off the banner stack)", () => {
    const flat: ApiCategory[] = [
      cat({
        id: "bags-root",
        name: "Bags",
        slug: "bags",
        collection_id: "bags-root",
        is_featured: true,
        image: "https://cdn.example/bags-banner.jpg",
      }),
      cat({
        id: "bags",
        name: "Bags",
        slug: "bags",
        collection_id: "bags-root",
        is_featured: true,
        image: "https://cdn.example/bags-banner.jpg",
        position: 0,
      }),
      cat({
        id: "homewear",
        name: "Homewear",
        slug: "homewear",
        is_featured: false,
        image: "https://cdn.example/homewear-banner.jpg",
        logo_url: "https://cdn.example/homewear.jpg",
        position: 1,
      }),
    ];

    const byHandle = new Map();
    const collectionById = new Map<string, ApiCategory>([
      [flat[0].id, flat[0]],
    ]);

    const plates = pickFeaturedStorefrontCategories(flat, {
      byHandle,
      collectionById,
    });

    expect(plates.map((p) => p.handle)).toEqual(["bags"]);
    expect(plates.find((p) => p.handle === "homewear")).toBeUndefined();
  });
});

describe("pickStorefrontCategoryChips", () => {
  it("lists every real category and skips synthetic collection wrappers", () => {
    const flat: ApiCategory[] = [
      cat({
        id: "eyewear-root",
        name: "Eyewear",
        slug: "eyewear",
        collection_id: "eyewear-root",
      }),
      cat({
        id: "sunglasses",
        name: "Sunglasses",
        slug: "sunglasses",
        collection_id: "eyewear-root",
        is_featured: true,
        position: 0,
      }),
      cat({
        id: "shoes",
        name: "shoes",
        slug: "shoes",
        is_featured: true,
        position: 1,
      }),
      cat({
        id: "homewear",
        name: "Homewear",
        slug: "homewear",
        is_featured: false,
        position: 2,
      }),
    ];

    const chips = pickStorefrontCategoryChips(flat, {
      sunglasses: 2,
      shoes: 0,
      homewear: 1,
    });

    expect(chips.map((c) => c.handle)).toEqual([
      "sunglasses",
      "shoes",
      "homewear",
    ]);
    expect(chips.find((c) => c.handle === "eyewear")).toBeUndefined();
    expect(chips.find((c) => c.handle === "sunglasses")?.products_count).toBe(
      2,
    );
  });
});
