import { describe, expect, it } from "vitest";
import { isOnSale } from "@/components/ProductPrice";

describe("isOnSale", () => {
  it("detects a real discount", () => {
    expect(isOnSale("750", "1000")).toBe(true);
    expect(isOnSale(750, 1000)).toBe(true);
  });

  it("ignores missing or non-discount compare prices", () => {
    expect(isOnSale("1000", null)).toBe(false);
    expect(isOnSale("1000", "1000")).toBe(false);
    expect(isOnSale("1000", "900")).toBe(false);
  });
});
