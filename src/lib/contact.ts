/** Contact helpers for footer / contact / about surfaces. */

export function whatsappHref(phoneOrWa: string): string {
  const digits = phoneOrWa.replace(/\D/g, "");
  return digits ? `https://wa.me/${digits}` : "";
}

export function telHref(phone: string): string | undefined {
  const digits = phone.replace(/[^\d+]/g, "");
  return digits ? `tel:${digits}` : undefined;
}

export function normalizeHttpUrl(url: string | null | undefined): string {
  const raw = (url || "").trim();
  if (!raw) return "";
  if (/^https?:\/\//i.test(raw)) return raw;
  return `https://${raw}`;
}

/** Custom Google Maps pin, or a search URL from the English address. */
export function mapsHref(
  mapsUrl: string | null | undefined,
  address: string | null | undefined,
): string {
  const custom = normalizeHttpUrl(mapsUrl);
  if (custom) return custom;
  const query = (address || "").trim();
  if (!query) return "";
  return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;
}
