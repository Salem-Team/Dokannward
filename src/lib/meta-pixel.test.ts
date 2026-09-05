// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from "vitest";
import { META_PIXEL_ID, trackMetaPurchase } from "./meta-pixel";

describe("meta-pixel", () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    delete (window as { fbq?: unknown }).fbq;
  });

  it("exports the configured pixel id", () => {
    expect(META_PIXEL_ID).toBe("2551828858665412");
  });

  it("fires Purchase with order payload when fbq is available", () => {
    const fbq = vi.fn();
    window.fbq = fbq;

    trackMetaPurchase({
      value: 281.25,
      currency: "EGP",
      contentIds: ["sku-1", "sku-2"],
      numItems: 3,
      orderId: "ZIB-10042",
    });

    expect(fbq).toHaveBeenCalledWith("track", "Purchase", {
      value: 281.25,
      currency: "EGP",
      content_ids: ["sku-1", "sku-2"],
      content_type: "product",
      num_items: 3,
      order_id: "ZIB-10042",
    });
  });

  it("no-ops when fbq is missing", () => {
    expect(() =>
      trackMetaPurchase({
        value: 10,
        currency: "EGP",
        contentIds: [],
        numItems: 1,
        orderId: "ZIB-1",
      }),
    ).not.toThrow();
  });
});
