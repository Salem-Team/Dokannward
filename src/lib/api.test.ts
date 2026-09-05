import { describe, expect, it, vi, beforeEach, afterEach } from "vitest";
import {
  ApiError,
  DEFAULT_CHECKOUT_SETTINGS,
  DEFAULT_CURRENCY,
  DEFAULT_INVENTORY_SETTINGS,
  DEFAULT_PAYMENT_METHOD,
  DEFAULT_STORE_SETTINGS,
  computeCheckoutTotals,
  fetchCheckoutSettings,
  fetchCurrency,
  fetchInventorySettings,
  fetchProducts,
  fetchStoreSettings,
  fetchTestimonials,
  formatCheckoutMessage,
  loadCheckoutSettings,
  loadInventorySettings,
  submitOrder,
} from "@/lib/api";

describe("computeCheckoutTotals", () => {
  it("applies the fixed shipping fee to every order", () => {
    const totals = computeCheckoutTotals(50, {
      standard_shipping_fee: 25,
      tax_rate: 10,
      tax_enabled: true,
    });

    expect(totals.shipping).toBe(25);
    expect(totals.tax).toBe(5);
    expect(totals.total).toBe(80);
  });

  it("does not waive shipping for a larger subtotal", () => {
    const totals = computeCheckoutTotals(100, {
      standard_shipping_fee: 25,
      tax_rate: 14,
      tax_enabled: true,
    });

    expect(totals.shipping).toBe(25);
    expect(totals.tax).toBe(14);
    expect(totals.total).toBe(139);
  });

  it("skips tax when disabled", () => {
    const totals = computeCheckoutTotals(200, {
      standard_shipping_fee: 40,
      tax_rate: 14,
      tax_enabled: false,
    });

    expect(totals.shipping).toBe(40);
    expect(totals.tax).toBe(0);
    expect(totals.total).toBe(240);
  });

  it("rounds money to two decimals", () => {
    const totals = computeCheckoutTotals(33.33, {
      standard_shipping_fee: 10,
      tax_rate: 8.5,
      tax_enabled: true,
    });

    expect(totals.tax).toBe(2.83);
    expect(totals.total).toBe(46.16);
  });

  it("matches the documented default settings shape", () => {
    expect(DEFAULT_CHECKOUT_SETTINGS).toMatchObject({
      standard_shipping_fee: expect.any(Number),
      shipping_company: expect.any(String),
      tax_rate: expect.any(Number),
      tax_enabled: true,
    });
  });
});

describe("api client resilience", () => {
  const originalFetch = globalThis.fetch;

  beforeEach(() => {
    vi.stubGlobal("fetch", vi.fn());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    globalThis.fetch = originalFetch;
  });

  it("returns an empty product list when the backend is offline", async () => {
    vi.mocked(fetch).mockRejectedValueOnce(new Error("network down"));
    await expect(fetchProducts()).resolves.toEqual([]);
  });

  it("returns empty testimonials when the request fails", async () => {
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: false,
      status: 503,
      json: async () => ({ message: "Unavailable" }),
    } as Response);

    await expect(fetchTestimonials()).resolves.toEqual([]);
  });

  it("falls back to default currency / store / checkout settings", async () => {
    vi.mocked(fetch).mockRejectedValue(new Error("offline"));

    await expect(fetchCurrency()).resolves.toEqual(DEFAULT_CURRENCY);
    await expect(fetchStoreSettings()).resolves.toEqual(DEFAULT_STORE_SETTINGS);
    await expect(fetchCheckoutSettings()).resolves.toEqual(DEFAULT_CHECKOUT_SETTINGS);
    await expect(fetchInventorySettings()).resolves.toEqual(
      DEFAULT_INVENTORY_SETTINGS,
    );
  });

  it("bypasses the fetch cache for financial checkout settings", async () => {
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({
        standard_shipping_fee: 65,
        shipping_company: "Bosta",
        tax_rate: 14,
        tax_enabled: true,
      }),
    } as Response);

    await expect(fetchCheckoutSettings({ fresh: true })).resolves.toMatchObject({
      standard_shipping_fee: 65,
      shipping_company: "Bosta",
      tax_rate: 14,
      tax_enabled: true,
    });
    expect(fetch).toHaveBeenCalledWith(
      expect.stringContaining("/settings/checkout"),
      expect.objectContaining({ cache: "no-store" }),
    );
  });

  it("preserves an intentionally blank shipping company", async () => {
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({
        standard_shipping_fee: 65,
        shipping_company: "",
        tax_rate: 14,
        tax_enabled: true,
      }),
    } as Response);

    await expect(fetchCheckoutSettings({ fresh: true })).resolves.toMatchObject({
      shipping_company: "",
    });
  });

  it("reports an unreachable admin API instead of inventing checkout defaults", async () => {
    vi.mocked(fetch).mockRejectedValueOnce(new Error("offline"));

    // The browser revalidator relies on null to keep the last known-good copy
    // rather than silently repricing the order with our fallbacks.
    await expect(loadCheckoutSettings({ fresh: true })).resolves.toBeNull();
  });

  it("normalizes inventory visibility so hidden stock never exposes quantities", async () => {
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({
        show_stock_status: false,
        show_stock_quantity: true,
      }),
    } as Response);

    await expect(loadInventorySettings({ fresh: true })).resolves.toEqual({
      show_stock_status: false,
      show_stock_quantity: false,
    });
  });

  it("ignores non-numeric checkout amounts instead of producing NaN totals", async () => {
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({
        standard_shipping_fee: "not-a-number",
        tax_rate: 0,
        tax_enabled: false,
      }),
    } as Response);

    await expect(loadCheckoutSettings({ fresh: true })).resolves.toMatchObject({
      standard_shipping_fee: DEFAULT_CHECKOUT_SETTINGS.standard_shipping_fee,
      tax_rate: 0,
      tax_enabled: false,
    });
  });

  it("keeps checkout payable when the admin sends a broken payment list", async () => {
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({
        payment_methods: [
          { key: "instapay", label: "InstaPay", instructions: " Send it " },
          { key: "instapay", label: "Duplicate" },
          { key: "", label: "No key" },
          { key: "visa" },
          "nonsense",
        ],
        default_payment_method: "visa",
      }),
    } as Response);

    const settings = await loadCheckoutSettings({ fresh: true });

    expect(settings?.payment_methods).toEqual([
      { key: "instapay", label: "InstaPay", instructions: "Send it" },
    ]);
    // Visa never survived normalisation, so the default snaps to a real option.
    expect(settings?.default_payment_method).toBe("instapay");
  });

  it("falls back to cash on delivery when no payment methods arrive", async () => {
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({ payment_methods: [] }),
    } as Response);

    const settings = await loadCheckoutSettings({ fresh: true });

    expect(settings?.payment_methods).toEqual([DEFAULT_PAYMENT_METHOD]);
    expect(settings?.default_payment_method).toBe("cash");
  });

  it("formats admin checkout messages without removing unknown placeholders", () => {
    expect(
      formatCheckoutMessage(
        "Free over {threshold}; add {remaining}. Keep {unknown}.",
        "fallback",
        { threshold: "LE 500", remaining: "LE 75" },
      ),
    ).toBe("Free over LE 500; add LE 75. Keep {unknown}.");
  });

  it("preserves blank live settings while filling critical brand assets", async () => {
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({ name: "  Dokan Ward Live  ", email: "" }),
    } as Response);

    const settings = await fetchStoreSettings();

    expect(settings).toMatchObject({
      name: "Dokan Ward Live",
      email: "",
      phone: "",
      whatsapp: "",
      description: "",
      announcement: "",
      logo: DEFAULT_STORE_SETTINGS.logo,
      logo_on_dark: DEFAULT_STORE_SETTINGS.logo_on_dark,
      seo_og_image: DEFAULT_STORE_SETTINGS.seo_og_image,
      social: { instagram: "", tiktok: "", facebook: "" },
    });
  });

  it("surfaces ApiError with validation details on checkout failure", async () => {
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: false,
      status: 422,
      json: async () => ({
        message: "The given data was invalid.",
        errors: { phone: ["Required"] },
      }),
    } as Response);

    const payload = {
      recipient_name: "A",
      phone: "",
      line_1: "1 St",
      city: "Cairo",
      latitude: 30.0444,
      longitude: 31.2357,
      items: [{ product_id: "1", name: "Bag", price: 10, qty: 1 }],
    };

    await expect(submitOrder(payload)).rejects.toBeInstanceOf(ApiError);

    vi.mocked(fetch).mockResolvedValueOnce({
      ok: false,
      status: 422,
      json: async () => ({
        message: "The given data was invalid.",
        errors: { phone: ["Required"] },
      }),
    } as Response);

    try {
      await submitOrder(payload);
      expect.unreachable("submitOrder should have thrown");
    } catch (err) {
      expect(err).toBeInstanceOf(ApiError);
      expect((err as ApiError).status).toBe(422);
      expect((err as ApiError).errors).toEqual({ phone: ["Required"] });
      expect((err as ApiError).message).toContain("invalid");
    }
  });

  it("posts checkout payloads as JSON without caching", async () => {
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: true,
      status: 201,
      json: async () => ({
        id: "ord-1",
        order_number: "ZBR-1",
        status: "pending",
        total_amount: 100,
      }),
    } as Response);

    const result = await submitOrder({
      recipient_name: "Buyer",
      phone: "+201000000000",
      line_1: "12 Nile",
      city: "Cairo",
      latitude: 30.0444,
      longitude: 31.2357,
      items: [{ product_id: "p1", name: "Tote", price: 100, qty: 1 }],
    });

    expect(result.order_number).toBe("ZBR-1");
    expect(fetch).toHaveBeenCalledWith(
      expect.stringContaining("/checkout"),
      expect.objectContaining({
        method: "POST",
        cache: "no-store",
        body: expect.stringContaining("Buyer"),
      }),
    );
  });
});
