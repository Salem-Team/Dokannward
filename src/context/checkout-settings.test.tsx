/** @vitest-environment jsdom */

import { afterEach, describe, expect, it, vi } from "vitest";
import { act, cleanup, render, screen, waitFor } from "@testing-library/react";
import { DEFAULT_CHECKOUT_SETTINGS, type CheckoutSettings } from "@/lib/api";

const loadCheckoutSettings = vi.hoisted(() => vi.fn());

vi.mock("@/lib/api", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/lib/api")>()),
  loadCheckoutSettings,
}));

import {
  CheckoutSettingsProvider,
  useCheckoutSettings,
} from "@/context/checkout-settings";

const STALE: CheckoutSettings = {
  standard_shipping_fee: 10,
  shipping_company: "Dokan Ward Delivery",
  tax_rate: 8.5,
  tax_enabled: true,
  payment_methods: [{ key: "cash", label: "Cash on delivery", instructions: "" }],
  default_payment_method: "cash",
};

const LIVE: CheckoutSettings = {
  standard_shipping_fee: 25,
  shipping_company: "Bosta",
  tax_rate: 1,
  tax_enabled: false,
  payment_methods: [
    { key: "cash", label: "Cash on delivery", instructions: "" },
    { key: "instapay", label: "InstaPay", instructions: "Send the total." },
  ],
  default_payment_method: "instapay",
};

function Probe() {
  const { settings, isLive } = useCheckoutSettings();
  return (
    <div>
      <span data-testid="shipping">{settings.standard_shipping_fee}</span>
      <span data-testid="company">{settings.shipping_company}</span>
      <span data-testid="rate">{settings.tax_rate}</span>
      <span data-testid="enabled">{String(settings.tax_enabled)}</span>
      <span data-testid="payment">{settings.default_payment_method}</span>
      <span data-testid="methods">
        {settings.payment_methods.map((m) => m.key).join(",")}
      </span>
      <span data-testid="live">{String(isLive)}</span>
    </div>
  );
}

const read = (id: string) => screen.getByTestId(id).textContent;

afterEach(cleanup);

describe("CheckoutSettingsProvider", () => {
  it("replaces a stale server snapshot with the live admin values", async () => {
    loadCheckoutSettings.mockResolvedValue(LIVE);

    render(
      <CheckoutSettingsProvider initial={STALE}>
        <Probe />
      </CheckoutSettingsProvider>,
    );

    // First paint uses the server value so there is no flash of empty pricing.
    expect(read("rate")).toBe("8.5");

    await waitFor(() => expect(read("rate")).toBe("1"));
    expect(read("shipping")).toBe("25");
    expect(read("company")).toBe("Bosta");
    expect(read("enabled")).toBe("false");
    expect(read("live")).toBe("true");
    expect(read("methods")).toBe("cash,instapay");
    expect(read("payment")).toBe("instapay");
    expect(loadCheckoutSettings).toHaveBeenCalledWith({ fresh: true });
  });

  it("keeps the server values when the admin API is unreachable", async () => {
    loadCheckoutSettings.mockResolvedValue(null);

    render(
      <CheckoutSettingsProvider initial={STALE}>
        <Probe />
      </CheckoutSettingsProvider>,
    );

    await waitFor(() => expect(loadCheckoutSettings).toHaveBeenCalled());
    expect(read("rate")).toBe("8.5");
    expect(read("live")).toBe("false");
  });

  it("re-confirms the values when the shopper returns to the tab", async () => {
    loadCheckoutSettings.mockResolvedValue(STALE);

    render(
      <CheckoutSettingsProvider initial={STALE}>
        <Probe />
      </CheckoutSettingsProvider>,
    );

    await waitFor(() => expect(loadCheckoutSettings).toHaveBeenCalledTimes(1));

    loadCheckoutSettings.mockResolvedValue(LIVE);
    document.dispatchEvent(new Event("visibilitychange"));

    await waitFor(() => expect(read("rate")).toBe("1"));
  });

  it("polls for admin edits while the tab stays open", async () => {
    vi.useFakeTimers();
    loadCheckoutSettings.mockResolvedValue(STALE);

    render(
      <CheckoutSettingsProvider initial={STALE}>
        <Probe />
      </CheckoutSettingsProvider>,
    );

    await act(async () => {
      await Promise.resolve();
    });
    expect(loadCheckoutSettings).toHaveBeenCalledTimes(1);

    loadCheckoutSettings.mockResolvedValue(LIVE);

    await act(async () => {
      await vi.advanceTimersByTimeAsync(5 * 60_000);
      await Promise.resolve();
    });

    expect(read("rate")).toBe("1");
    vi.useRealTimers();
  });

  it("falls back to documented defaults without a server snapshot", () => {
    loadCheckoutSettings.mockResolvedValue(null);

    render(
      <CheckoutSettingsProvider>
        <Probe />
      </CheckoutSettingsProvider>,
    );

    expect(read("rate")).toBe(String(DEFAULT_CHECKOUT_SETTINGS.tax_rate));
  });
});
