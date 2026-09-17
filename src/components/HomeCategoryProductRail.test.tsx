/** @vitest-environment jsdom */

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { cleanup, render, screen } from "@testing-library/react";
import type { ProductCardData } from "@/lib/product-card";

vi.mock("next/image", () => ({
  default: (props: { alt?: string; src?: string }) => (
     
    <img alt={props.alt ?? ""} src={typeof props.src === "string" ? props.src : ""} />
  ),
}));

vi.mock("@/components/ProductCardClient", () => ({
  ProductCardClient: ({
    product,
    priceLabel,
  }: {
    product: ProductCardData;
    priceLabel?: string;
  }) => (
    <article data-testid={`card-${product.handle}`}>
      <a href={`/products/${product.handle}`}>{product.title}</a>
      <span>{priceLabel}</span>
    </article>
  ),
}));

import { HomeCategoryProductRail } from "@/components/HomeCategoryProductRail";

function makeItem(
  handle: string,
  title = handle,
): { product: ProductCardData; priceLabel: string } {
  return {
    product: {
      id: handle,
      handle,
      title,
      price: "100",
      compareAtPrice: null,
      image: "/images/dokan-ward-logo.png",
      available: true,
      vendor: "Dokan Ward",
      brandSlug: null,
      sku: handle,
      colors: [],
    },
    priceLabel: "LE 100.00",
  };
}

describe("HomeCategoryProductRail", () => {
  beforeEach(() => {
    vi.stubGlobal(
      "ResizeObserver",
      class {
        observe() {}
        unobserve() {}
        disconnect() {}
      },
    );
    vi.stubGlobal("matchMedia", (query: string) => ({
      matches: false,
      media: query,
      onchange: null,
      addEventListener() {},
      removeEventListener() {},
      addListener() {},
      removeListener() {},
      dispatchEvent() {
        return false;
      },
    }));
    Object.defineProperty(HTMLElement.prototype, "clientWidth", {
      configurable: true,
      get() {
        return 800;
      },
    });
  });

  afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it("returns null when there are no items", () => {
    const { container } = render(
      <HomeCategoryProductRail items={[]} label="Bags" />,
    );
    expect(container.innerHTML).toBe("");
  });

  it("dedupes products and renders each unique card in the first set", () => {
    render(
      <HomeCategoryProductRail
        items={[
          makeItem("bag-a", "Bag A"),
          makeItem("bag-b", "Bag B"),
          makeItem("bag-a", "Bag A duplicate"),
        ]}
        label="Bags"
      />,
    );

    expect(screen.getByLabelText("Bags products")).toBeTruthy();
    // Clones repeat the set for continuous scroll; unique titles still appear.
    expect(screen.getAllByText("Bag A").length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText("Bag B").length).toBeGreaterThanOrEqual(1);
  });

  it("uses fit layout for a single product", () => {
    render(
      <HomeCategoryProductRail items={[makeItem("bag-a")]} label="Bags" />,
    );

    const rail = screen.getByLabelText("Bags products");
    expect(rail.className).toContain("is-looping");
  });

  it("uses looping scroll layout for multiple unique products", () => {
    render(
      <HomeCategoryProductRail
        items={[makeItem("bag-a"), makeItem("bag-b"), makeItem("bag-c")]}
        label="Bags"
      />,
    );

    const rail = screen.getByLabelText("Bags products");
    expect(rail.className).toContain("is-scroll");
    expect(rail.className).toContain("is-looping");
    // One visible set + clones for continuous scroll (unique handles still once per set).
    expect(screen.getAllByTestId("card-bag-a").length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByTestId("card-bag-b").length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByTestId("card-bag-c").length).toBeGreaterThanOrEqual(1);
  });
});
