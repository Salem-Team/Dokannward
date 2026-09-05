import { describe, expect, it } from "vitest";
import {
  categoryPublicImageFallback,
  isLaravelStorageSrc,
  lineImageCandidates,
  pickStorefrontImage,
  rewriteStorefrontMediaUrl,
  shouldUnoptimizeStorefrontImage,
  storefrontImageSrc,
} from "@/lib/media";

describe("storefront media helpers", () => {
  it("drops ui-avatars placeholders", () => {
    expect(
      storefrontImageSrc("https://ui-avatars.com/api/?name=Dokan Ward"),
    ).toBeNull();
  });

  it("keeps Laravel storage urls (with cache busters)", () => {
    const src =
      "https://dokannward.com/storage/categories/a.jpg?v=1700000000";
    expect(storefrontImageSrc(src)).toBe(src);
    expect(isLaravelStorageSrc(src)).toBe(true);
    expect(shouldUnoptimizeStorefrontImage(src)).toBe(true);
  });

  it("detects relative storage paths and svgs", () => {
    expect(isLaravelStorageSrc("/storage/products/x.jpg")).toBe(true);
    expect(shouldUnoptimizeStorefrontImage("/images/logo.svg")).toBe(true);
    expect(shouldUnoptimizeStorefrontImage("/images/hero.jpg")).toBe(false);
  });

  it("keeps localhost storage URLs when the API is local", () => {
    const prev = process.env.NEXT_PUBLIC_API_URL;
    process.env.NEXT_PUBLIC_API_URL = "http://127.0.0.1:8001/api";
    try {
      // Bare APP_URL (no port) must remount onto the API origin — not left on :80.
      expect(
        rewriteStorefrontMediaUrl(
          "http://localhost/storage/products/chanel.jpg?v=1",
        ),
      ).toBe("http://127.0.0.1:8001/storage/products/chanel.jpg?v=1");

      expect(
        rewriteStorefrontMediaUrl(
          "http://localhost:8000/storage/products/chanel.jpg?v=1",
        ),
      ).toBe("http://127.0.0.1:8001/storage/products/chanel.jpg?v=1");

      expect(
        storefrontImageSrc("http://127.0.0.1:8001/storage/products/x.jpg"),
      ).toBe("http://127.0.0.1:8001/storage/products/x.jpg");

      // Seeded category covers prefer Next public/images/categories.
      expect(
        storefrontImageSrc(
          "http://localhost/storage/categories/dokannward/bakhoor-burners.png?v=9",
        ),
      ).toBe("/images/categories/bakhoor-burners.webp");
    } finally {
      process.env.NEXT_PUBLIC_API_URL = prev;
    }
  });

  it("maps category storage paths to the public images pack", () => {
    expect(
      categoryPublicImageFallback(
        "http://localhost/storage/categories/dokannward/ramadan-products.png?v=1",
      ),
    ).toBe("/images/categories/ramadan-products.webp");
    expect(
      categoryPublicImageFallback("/storage/categories/dokannward/vases.png"),
    ).toBe("/images/categories/vases.webp");
  });

  it("rewrites localhost storage URLs to the live site when API is remote", () => {
    const prev = process.env.NEXT_PUBLIC_API_URL;
    process.env.NEXT_PUBLIC_API_URL = "https://dokannward.com/api";
    try {
      expect(
        rewriteStorefrontMediaUrl(
          "http://localhost:8000/storage/products/chanel.jpg?v=1",
        ),
      ).toBe("https://dokannward.com/storage/products/chanel.jpg?v=1");

      expect(
        storefrontImageSrc("http://127.0.0.1:8001/storage/products/x.jpg"),
      ).toBe("https://dokannward.com/storage/products/x.jpg");
    } finally {
      process.env.NEXT_PUBLIC_API_URL = prev;
    }
  });

  it("picks the first usable line image and skips empty color photos", () => {
    expect(
      pickStorefrontImage(null, "", "https://dokannward.com/storage/products/a.jpg"),
    ).toBe("https://dokannward.com/storage/products/a.jpg");

    expect(
      lineImageCandidates({
        colorImage: null,
        productImage: "https://dokannward.com/storage/products/a.jpg",
        productImages: ["https://dokannward.com/storage/products/b.jpg"],
        fallback: "/images/dokan-ward-logo.png",
      }),
    ).toEqual([
      "https://dokannward.com/storage/products/a.jpg",
      "https://dokannward.com/storage/products/b.jpg",
      "/images/dokan-ward-logo.png",
    ]);
  });
});
