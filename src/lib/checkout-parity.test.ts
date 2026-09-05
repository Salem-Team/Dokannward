import { describe, expect, it } from "vitest";
import {
  DEFAULT_CHECKOUT_SETTINGS,
  DEFAULT_CURRENCY,
  DEFAULT_STORE_SETTINGS,
  computeCheckoutTotals,
} from "@/lib/api";

/**
 * Parity checks against admin SettingsController::DEFAULTS and
 * CheckoutController::computeShippingAndTax — keep storefront preview
 * math identical to what Laravel bills at /api/checkout.
 */
describe("storefront ↔ admin checkout parity", () => {
  it("mirrors admin default shipping / tax / currency / store identity", () => {
    expect(DEFAULT_CHECKOUT_SETTINGS).toMatchObject({
      standard_shipping_fee: 10,
      shipping_company: "Dokan Ward Delivery",
      tax_rate: 8.5,
      tax_enabled: true,
    });
    expect(DEFAULT_CURRENCY).toEqual({
      code: "EGP",
      symbol: "LE",
      position: "before",
    });
    expect(DEFAULT_STORE_SETTINGS.name).toBe("Dokan Ward");
    expect(DEFAULT_STORE_SETTINGS.email).toBe("hello@dokannward.com");
    expect(DEFAULT_STORE_SETTINGS.address_label_ar).toBe("نخدم كل مصر");
    expect(DEFAULT_STORE_SETTINGS.address_ar).toContain("مصر");
  });

  it("matches Laravel rounding for the default tax rate", () => {
    // PHP: round($subtotal * ($rate / 100), 2)
    // JS:  Math.round(subtotal * (rate / 100) * 100) / 100
    // Shipping remains fixed regardless of subtotal.
    const { shipping, tax, total } = computeCheckoutTotals(
      250,
      DEFAULT_CHECKOUT_SETTINGS,
    );

    expect(shipping).toBe(10);
    expect(tax).toBe(21.25); // 250 * 0.085
    expect(total).toBe(281.25);
  });

  it("matches the CatalogApiTest checkout scenario (250 + 25 ship + 10% tax)", () => {
    const totals = computeCheckoutTotals(250, {
      standard_shipping_fee: 25,
      tax_rate: 10,
      tax_enabled: true,
    });

    expect(totals).toEqual({ shipping: 25, tax: 25, total: 300 });
  });
});
