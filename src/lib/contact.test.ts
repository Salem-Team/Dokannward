import { describe, expect, it } from "vitest";
import { mapsHref, normalizeHttpUrl } from "@/lib/contact";

describe("mapsHref", () => {
  it("prefers a custom Google Maps pin", () => {
    expect(
      mapsHref(
        "maps.app.goo.gl/dokanward",
        "The 5th Settlement, New Cairo",
      ),
    ).toBe("https://maps.app.goo.gl/dokanward");
  });

  it("falls back to a Maps search from the English address", () => {
    expect(mapsHref("", "The 5th Settlement, New Cairo")).toBe(
      "https://www.google.com/maps/search/?api=1&query=The%205th%20Settlement%2C%20New%20Cairo",
    );
  });

  it("returns empty when neither pin nor address is set", () => {
    expect(mapsHref("", "")).toBe("");
    expect(normalizeHttpUrl("")).toBe("");
  });
});
