"use client";

import { StorefrontImage } from "@/components/StorefrontImage";
import Link from "next/link";
import { useMemo, useState } from "react";
import {
  hrefForStorefrontCollection,
  type SearchProduct,
  type StorefrontCollection,
} from "@/lib/catalog";
import { useCurrency } from "@/context/currency";
import { useLocale } from "@/context/locale";
import { IconSearch } from "@/components/Icons";

type SlimCollection = {
  handle: string;
  title: string;
  kind: StorefrontCollection["kind"];
};

/** Receives a slim catalog already fetched server-side (ISR-cached). */
export function SearchClient({
  products,
  collections,
}: {
  products: SearchProduct[];
  collections: SlimCollection[];
}) {
  const [query, setQuery] = useState("");
  const { format } = useCurrency();
  const { t } = useLocale();

  const productResults = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return products;
    return products.filter(
      (p) =>
        p.title.toLowerCase().includes(q) ||
        p.handle.toLowerCase().includes(q) ||
        p.vendor.toLowerCase().includes(q),
    );
  }, [query, products]);

  const collectionResults = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return [];
    return collections.filter((c) => c.title.toLowerCase().includes(q));
  }, [query, collections]);

  return (
    <div className="container py-10 md:py-14">
      <h1 className="heading text-3xl md:text-5xl mb-8 text-center">
        {t("search.pageTitle")}
      </h1>
      <label className="search-field">
        <span className="search-field__icon" aria-hidden="true">
          <IconSearch size={18} />
        </span>
        <input
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder={t("search.pagePlaceholder")}
          className="search-field__input"
          autoFocus
        />
      </label>

      {collectionResults.length > 0 && (
        <section className="mb-12">
          <h2 className="text-xs uppercase tracking-[0.15em] opacity-50 mb-4">
            {t("nav.collections")}
          </h2>
          <ul className="space-y-2">
            {collectionResults.map((c) => (
              <li key={`${c.kind}-${c.handle}`}>
                <Link
                  href={hrefForStorefrontCollection(c)}
                  className="link-underline hover:opacity-60"
                >
                  {c.title}
                </Link>
              </li>
            ))}
          </ul>
        </section>
      )}

      <section>
        <h2 className="text-xs uppercase tracking-[0.15em] opacity-50 mb-4">
          {t("search.products")}
        </h2>
        <ul className="space-y-3 max-w-2xl">
          {productResults.map((p) => (
            <li key={p.handle}>
              <Link
                href={`/products/${p.handle}`}
                className="flex items-center gap-4 hover:bg-black/[0.03] p-2 -mx-2 transition-colors"
              >
                <StorefrontImage
                  src={p.image || ""}
                  alt={p.title}
                  width={64}
                  height={64}
                  className="w-16 h-16 object-cover bg-[var(--color-paper)] media-mono"
                />
                <div>
                  <div>{p.title}</div>
                  <div className="text-sm opacity-70">{format(p.price)}</div>
                </div>
              </Link>
            </li>
          ))}
          {productResults.length === 0 && (
            <li className="text-sm opacity-50">{t("search.noProducts")}</li>
          )}
        </ul>
      </section>
    </div>
  );
}
