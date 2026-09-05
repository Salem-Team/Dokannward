import type { Metadata } from "next";
import {
  getAllProducts,
  getAllStorefrontCollections,
  toSearchProduct,
} from "@/lib/catalog";
import { SearchClient } from "./SearchClient";
import { pageMetadata } from "@/lib/seo";
import { getServerLocale } from "@/lib/i18n/server";
import { t } from "@/lib/i18n";

export const revalidate = 600;

export async function generateMetadata(): Promise<Metadata> {
  const locale = await getServerLocale();
  return pageMetadata({
    path: "/search",
    title: t(locale, "search.metaTitle"),
    description: t(locale, "search.metaDesc"),
    index: false,
  });
}

export default async function SearchPage() {
  const [products, collections] = await Promise.all([
    getAllProducts(),
    getAllStorefrontCollections(),
  ]);
  return (
    <SearchClient
      products={products.map(toSearchProduct)}
      collections={collections.map((c) => ({
        handle: c.handle,
        title: c.display_title || c.title,
        kind: c.kind,
      }))}
    />
  );
}
