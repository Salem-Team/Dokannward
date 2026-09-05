import { describe, expect, it } from "vitest";
import {
  EGYPT_CENTER,
  clamp,
  cityFromPlaceName,
  formFromPlace,
  formatCoords,
  googleMapsUrl,
  inferCityFromCoords,
  normalizeEgyptCity,
  project,
  tileUrl,
  unproject,
  wrapLng,
} from "./geo";

describe("geo projection", () => {
  it("round-trips Cairo through web mercator", () => {
    const zoom = 16;
    const world = project(EGYPT_CENTER, zoom);
    const back = unproject(world.x, world.y, zoom);

    expect(back.lat).toBeCloseTo(EGYPT_CENTER.lat, 5);
    expect(back.lng).toBeCloseTo(EGYPT_CENTER.lng, 5);
  });

  it("builds an OSM tile URL inside the zoom grid", () => {
    expect(tileUrl(2, 1, 1)).toBe("https://tile.openstreetmap.org/2/1/1.png");
    expect(tileUrl(2, 5, 0)).toBe("https://tile.openstreetmap.org/2/1/0.png");
  });

  it("formats coordinates and map links", () => {
    expect(formatCoords({ lat: 30.0444, lng: 31.2357 }, 4)).toContain("30.0444° N");
    expect(googleMapsUrl(EGYPT_CENTER)).toContain("30.0444");
    expect(clamp(21, 0, 19)).toBe(19);
    expect(wrapLng(190)).toBe(-170);
  });
});

describe("checkout address autofill", () => {
  it("normalizes OSM city labels onto the Egypt list", () => {
    expect(normalizeEgyptCity("Cairo Governorate")).toBe("Cairo");
    expect(normalizeEgyptCity("el maadi")).toBe("Maadi");
    expect(normalizeEgyptCity("Zamalek")).toBe("Zamalek");
  });

  it("fills street and city from a geocoded pin", () => {
    expect(
      formFromPlace({
        place_name: "Abu El Feda, Zamalek, Cairo Governorate, Egypt",
        line_1: "Abu El Feda, Zamalek",
        city: "Cairo Governorate",
        postal_code: "11211",
        latitude: 30.06,
        longitude: 31.22,
      }),
    ).toEqual({
      address: "Abu El Feda, Zamalek",
      city: "Zamalek",
      postal_code: "11211",
    });
  });

  it("falls back to the display name when the street is missing", () => {
    expect(
      formFromPlace({
        place_name: "Tahrir Square, Cairo, Egypt",
        line_1: null,
        city: "Cairo",
        postal_code: null,
        latitude: 30.04,
        longitude: 31.23,
      }).address,
    ).toBe("Tahrir Square");
  });

  it("maps Fifth Settlement pins to New Cairo", () => {
    expect(inferCityFromCoords({ lat: 30.0285, lng: 31.432 })).toBe("New Cairo");
    expect(inferCityFromCoords({ lat: 30.055, lng: 31.22 })).toBe("Zamalek");
    expect(
      formFromPlace({
        place_name: "Southern Academy Area, Cairo, Egypt",
        line_1: "186 Amr Ibn El Aas Street",
        city: "Cairo",
        postal_code: "11865",
        latitude: 30.0285,
        longitude: 31.432,
      }).city,
    ).toBe("New Cairo");
  });

  it("reads city names from the full place label", () => {
    expect(cityFromPlaceName("Road 9, Maadi, Cairo, Egypt")).toBe("Maadi");
  });
});
