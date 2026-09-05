// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from "vitest";
import {
  GeolocationReadError,
  geolocationHint,
  geolocationHintKey,
  readCheckoutGeolocation,
} from "./geolocation";

describe("readCheckoutGeolocation", () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("returns the first successful position", async () => {
    const position = {
      coords: { latitude: 30.0285, longitude: 31.432, accuracy: 20 },
    } as GeolocationPosition;

    vi.stubGlobal("navigator", {
      geolocation: {
        getCurrentPosition: vi.fn((success: PositionCallback) => success(position)),
        watchPosition: vi.fn(),
        clearWatch: vi.fn(),
      },
    });

    const result = await readCheckoutGeolocation();
    expect(result.coords.latitude).toBe(30.0285);
  });

  it("throws permission denied without retrying", async () => {
    const denied = { code: 1, PERMISSION_DENIED: 1, TIMEOUT: 3, message: "denied" };

    vi.stubGlobal("navigator", {
      geolocation: {
        getCurrentPosition: vi.fn((_ok: PositionCallback, fail: PositionErrorCallback) =>
          fail(denied as GeolocationPositionError),
        ),
        watchPosition: vi.fn(),
        clearWatch: vi.fn(),
      },
    });

    await expect(readCheckoutGeolocation()).rejects.toMatchObject({ code: 1 });
  });

  it("maps failure codes to i18n message keys", () => {
    expect(geolocationHint(1)).toBe("geo.denied");
    expect(geolocationHint(2)).toBe("geo.unavailable");
    expect(geolocationHint(3)).toBe("geo.timeout");
    expect(geolocationHintKey(0)).toBe("geo.unsupported");
    expect(geolocationHintKey(1)).toBe("geo.denied");
    expect(geolocationHintKey(2)).toBe("geo.unavailable");
    expect(geolocationHintKey(3)).toBe("geo.timeout");
  });

  it("wraps unsupported browsers", async () => {
    vi.stubGlobal("navigator", {});
    await expect(readCheckoutGeolocation()).rejects.toBeInstanceOf(GeolocationReadError);
  });
});
