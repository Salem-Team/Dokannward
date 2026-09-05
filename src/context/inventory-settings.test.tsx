/** @vitest-environment jsdom */

import { cleanup, render, screen, waitFor } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

const loadInventorySettings = vi.hoisted(() => vi.fn());

vi.mock("@/lib/api", async (importOriginal) => ({
  ...(await importOriginal<typeof import("@/lib/api")>()),
  loadInventorySettings,
}));

import {
  InventorySettingsProvider,
  useInventorySettings,
} from "@/context/inventory-settings";

function Probe() {
  const settings = useInventorySettings();
  return (
    <span data-testid="stock">
      {String(settings.show_stock_status)}:
      {String(settings.show_stock_quantity)}
    </span>
  );
}

afterEach(cleanup);

describe("InventorySettingsProvider", () => {
  it("replaces the server snapshot with live admin visibility", async () => {
    loadInventorySettings.mockResolvedValue({
      show_stock_status: true,
      show_stock_quantity: true,
    });

    render(
      <InventorySettingsProvider
        initial={{ show_stock_status: false, show_stock_quantity: false }}
      >
        <Probe />
      </InventorySettingsProvider>,
    );

    expect(screen.getByTestId("stock").textContent).toBe("false:false");
    await waitFor(() =>
      expect(screen.getByTestId("stock").textContent).toBe("true:true"),
    );
    expect(loadInventorySettings).toHaveBeenCalledWith({ fresh: true });
  });
});
