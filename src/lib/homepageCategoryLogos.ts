import type { HomepageCategoryLogo } from "@/lib/catalog";

/** Prefer live admin logos only — never invent local mock plates. Dedupes by handle. */
export function resolveHomepageCategoryLogos(
  live: HomepageCategoryLogo[],
): HomepageCategoryLogo[] {
  const seen = new Set<string>();
  const unique: HomepageCategoryLogo[] = [];
  for (const item of live) {
    const key = item.handle || item.href || item.title;
    if (seen.has(key)) continue;
    seen.add(key);
    unique.push(item);
  }
  return unique;
}
