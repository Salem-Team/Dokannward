export const EGYPT_CENTER = { lat: 30.0444, lng: 31.2357 } as const;

export const GEO_ZOOM = {
  min: 4,
  max: 19,
  city: 12,
  street: 16,
  building: 18,
} as const;

export type LatLng = { lat: number; lng: number };

export type GeoPlace = {
  place_name: string | null;
  line_1: string | null;
  city: string | null;
  postal_code: string | null;
  latitude: number;
  longitude: number;
};

export type CheckoutLocation = {
  latitude: number;
  longitude: number;
  place_name: string | null;
  source: "map" | "gps" | "search";
};

export const EGYPT_CITIES = [
  "Cairo",
  "Giza",
  "Alexandria",
  "New Cairo",
  "6th of October",
  "Sheikh Zayed",
  "Maadi",
  "Heliopolis",
  "Nasr City",
  "Zamalek",
  "Mohandessin",
  "Helwan",
  "Shubra El Kheima",
  "Qalyub",
  "Banha",
  "Mansoura",
  "Tanta",
  "Zagazig",
  "Damietta",
  "Port Said",
  "Ismailia",
  "Suez",
  "Kafr El Sheikh",
  "Damanhur",
  "El Mahalla El Kubra",
  "Shibin El Kom",
  "Fayoum",
  "Beni Suef",
  "Minya",
  "Assiut",
  "Sohag",
  "Qena",
  "Luxor",
  "Aswan",
  "Hurghada",
  "El Gouna",
  "Sharm El Sheikh",
  "Dahab",
  "Marsa Alam",
  "Marsa Matrouh",
  "Arish",
] as const;

export function clamp(value: number, min: number, max: number): number {
  return Math.min(max, Math.max(min, value));
}

export function wrapLng(lng: number): number {
  let wrapped = lng;
  while (wrapped > 180) wrapped -= 360;
  while (wrapped < -180) wrapped += 360;
  return wrapped;
}

export function clampLat(lat: number): number {
  return clamp(lat, -85.05112878, 85.05112878);
}

export function worldSize(zoom: number): number {
  return 256 * 2 ** zoom;
}

/** Web Mercator world pixels for a lon/lat at a zoom level. */
export function project(point: LatLng, zoom: number): { x: number; y: number } {
  const lat = clampLat(point.lat);
  const sin = Math.sin((lat * Math.PI) / 180);
  const size = worldSize(zoom);
  return {
    x: ((point.lng + 180) / 360) * size,
    y: (0.5 - Math.log((1 + sin) / (1 - sin)) / (4 * Math.PI)) * size,
  };
}

export function unproject(x: number, y: number, zoom: number): LatLng {
  const size = worldSize(zoom);
  const wrappedX = ((x % size) + size) % size;
  const clampedY = clamp(y, 0, size);
  const n = Math.PI - (2 * Math.PI * clampedY) / size;
  return {
    lat: (180 / Math.PI) * Math.atan(0.5 * (Math.exp(n) - Math.exp(-n))),
    lng: wrapLng((wrappedX / size) * 360 - 180),
  };
}

export function tileUrl(z: number, x: number, y: number): string {
  const n = 2 ** z;
  const tx = ((x % n) + n) % n;
  const ty = clamp(y, 0, n - 1);
  return `https://tile.openstreetmap.org/${z}/${tx}/${ty}.png`;
}

export function formatCoords(point: LatLng, digits = 5): string {
  const ns = point.lat >= 0 ? "N" : "S";
  const ew = point.lng >= 0 ? "E" : "W";
  return `${Math.abs(point.lat).toFixed(digits)}° ${ns} · ${Math.abs(point.lng).toFixed(digits)}° ${ew}`;
}

export function googleMapsUrl(point: LatLng): string {
  return `https://www.google.com/maps?q=${point.lat},${point.lng}`;
}

/** Coarse Egypt delivery zones when Nominatim labels a pin as "Cairo". */
export function inferCityFromCoords(point: LatLng): string | null {
  const { lat, lng } = point;
  if (lat >= 29.9 && lat <= 30.15 && lng >= 31.35 && lng <= 31.62) return "New Cairo";
  if (lat >= 30.03 && lat <= 30.08 && lng >= 31.31 && lng <= 31.4) return "Nasr City";
  if (lat >= 30.08 && lat <= 30.13 && lng >= 31.3 && lng <= 31.37) return "Heliopolis";
  if (lat >= 30.05 && lat <= 30.069 && lng >= 31.215 && lng <= 31.235) return "Zamalek";
  if (lat >= 29.94 && lat <= 30.01 && lng >= 31.25 && lng <= 31.33) return "Maadi";
  if (lat >= 30.04 && lat <= 30.065 && lng >= 31.19 && lng <= 31.22) return "Mohandessin";
  if (lat >= 30.0 && lat <= 30.09 && lng >= 30.94 && lng <= 31.06) return "Sheikh Zayed";
  if (lat >= 29.88 && lat <= 30.04 && lng >= 30.84 && lng <= 31.02) return "6th of October";
  return null;
}

export function cityFromPlaceName(placeName: string | null | undefined): string {
  if (!placeName) return "";
  for (const part of placeName.split(",").map((segment) => segment.trim())) {
    const normalized = normalizeEgyptCity(part);
    if (!normalized || foldLabel(normalized) === "egypt") continue;
    if ((EGYPT_CITIES as readonly string[]).includes(normalized)) {
      return normalized;
    }
  }
  return "";
}

export function fallbackGeoPlace(point: LatLng): GeoPlace {
  const label = formatCoords(point);
  const inferred = inferCityFromCoords(point);
  return {
    place_name: label,
    line_1: `Pinned location near ${formatCoords(point, 4)}`,
    city: inferred,
    postal_code: null,
    latitude: point.lat,
    longitude: point.lng,
  };
}

const CITY_ALIASES: Record<string, (typeof EGYPT_CITIES)[number]> = {
  cairo: "Cairo",
  "al qahirah": "Cairo",
  "cairo governorate": "Cairo",
  giza: "Giza",
  "giza governorate": "Giza",
  alexandria: "Alexandria",
  alex: "Alexandria",
  "new cairo": "New Cairo",
  "new cairo city": "New Cairo",
  tagamoa: "New Cairo",
  "el tagamoa": "New Cairo",
  "fifth settlement": "New Cairo",
  "first settlement": "New Cairo",
  akademeya: "New Cairo",
  academy: "New Cairo",
  lotus: "New Cairo",
  "6th of october": "6th of October",
  "6 october": "6th of October",
  "sheikh zayed": "Sheikh Zayed",
  maadi: "Maadi",
  "el maadi": "Maadi",
  heliopolis: "Heliopolis",
  "misr el gedida": "Heliopolis",
  "nasr city": "Nasr City",
  zamalek: "Zamalek",
  mohandessin: "Mohandessin",
  mohandiseen: "Mohandessin",
  helwan: "Helwan",
  "shubra el kheima": "Shubra El Kheima",
  qalyub: "Qalyub",
  banha: "Banha",
  mansoura: "Mansoura",
  tanta: "Tanta",
  zagazig: "Zagazig",
  damietta: "Damietta",
  "port said": "Port Said",
  ismailia: "Ismailia",
  suez: "Suez",
  "kafr el sheikh": "Kafr El Sheikh",
  damanhur: "Damanhur",
  mahalla: "El Mahalla El Kubra",
  "el mahalla el kubra": "El Mahalla El Kubra",
  "shibin el kom": "Shibin El Kom",
  fayoum: "Fayoum",
  faiyum: "Fayoum",
  "beni suef": "Beni Suef",
  minya: "Minya",
  assiut: "Assiut",
  asyut: "Assiut",
  sohag: "Sohag",
  qena: "Qena",
  luxor: "Luxor",
  aswan: "Aswan",
  hurghada: "Hurghada",
  "el gouna": "El Gouna",
  "sharm el sheikh": "Sharm El Sheikh",
  dahab: "Dahab",
  "marsa alam": "Marsa Alam",
  "marsa matrouh": "Marsa Matrouh",
  arish: "Arish",
};

function foldLabel(value: string): string {
  return value
    .toLowerCase()
    .replace(/[-_]/g, " ")
    .replace(/\b(governorate|city|محافظة|مدينة)\b/gi, " ")
    .replace(/\s+/g, " ")
    .trim();
}

export function normalizeEgyptCity(value: string | null | undefined): string {
  const raw = (value ?? "").trim();
  if (!raw) return "";
  if ((EGYPT_CITIES as readonly string[]).includes(raw)) return raw;

  const folded = foldLabel(raw);
  if (CITY_ALIASES[folded]) return CITY_ALIASES[folded];

  const exact = EGYPT_CITIES.find((city) => foldLabel(city) === folded);
  return exact ?? raw;
}

export function streetFromPlace(place: GeoPlace): string {
  const city = normalizeEgyptCity(place.city);
  if (place.line_1?.trim()) {
    return place.line_1.trim();
  }

  const parts = (place.place_name ?? "")
    .split(",")
    .map((part) => part.trim())
    .filter(Boolean)
    .filter((part) => {
      const folded = foldLabel(part);
      return folded !== "egypt" && folded !== "eg" && folded !== foldLabel(city);
    });

  return parts.slice(0, 2).join(", ");
}

export function formFromPlace(place: GeoPlace): {
  address: string;
  city: string;
  postal_code: string;
} {
  const fromCity = normalizeEgyptCity(place.city);
  const fromCoords = inferCityFromCoords({ lat: place.latitude, lng: place.longitude });
  const fromName = cityFromPlaceName(place.place_name);
  const city =
    fromCity === "Cairo" && fromCoords && fromCoords !== "Cairo"
      ? fromCoords
      : fromCity || fromCoords || fromName;

  return {
    address: streetFromPlace({ ...place, city: city || place.city }),
    city,
    postal_code: (place.postal_code ?? "").replace(/\s+/g, ""),
  };
}
