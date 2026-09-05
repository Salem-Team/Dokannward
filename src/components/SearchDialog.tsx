"use client";

import { StorefrontImage } from "@/components/StorefrontImage";
import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import type { SearchProduct } from "@/lib/catalog";
import { useCurrency } from "@/context/currency";
import { useLocale } from "@/context/locale";
import { IconClose, IconSearch } from "@/components/Icons";

/** Module cache — second open is instant without another round-trip. */
let cachedCatalog: SearchProduct[] | null = null;
let catalogInflight: Promise<SearchProduct[]> | null = null;

function loadSearchCatalog(): Promise<SearchProduct[]> {
  if (cachedCatalog) return Promise.resolve(cachedCatalog);
  if (catalogInflight) return catalogInflight;

  catalogInflight = fetch("/api/catalog-search")
    .then((res) => (res.ok ? res.json() : { data: [] }))
    .then((body) => {
      const data: SearchProduct[] = Array.isArray(body?.data) ? body.data : [];
      cachedCatalog = data;
      return data;
    })
    .catch(() => {
      const empty: SearchProduct[] = [];
      cachedCatalog = empty;
      return empty;
    })
    .finally(() => {
      catalogInflight = null;
    });

  return catalogInflight;
}

export function SearchDialog({
  open,
  onClose,
}: {
  open: boolean;
  onClose: () => void;
}) {
  const [query, setQuery] = useState("");
  const [products, setProducts] = useState<SearchProduct[]>(cachedCatalog ?? []);
  const [loading, setLoading] = useState(!cachedCatalog);
  const { format } = useCurrency();
  const { t } = useLocale();

  useEffect(() => {
    if (!open) {
      setQuery("");
      return;
    }

    let cancelled = false;
    if (cachedCatalog) {
      setProducts(cachedCatalog);
      setLoading(false);
      return;
    }

    setLoading(true);
    loadSearchCatalog().then((data) => {
      if (cancelled) return;
      setProducts(data);
      setLoading(false);
    });

    return () => {
      cancelled = true;
    };
  }, [open]);

  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") onClose();
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [open, onClose]);

  const results = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return products;
    return products.filter(
      (p) =>
        p.title.toLowerCase().includes(q) ||
        p.handle.toLowerCase().includes(q) ||
        p.vendor.toLowerCase().includes(q),
    );
  }, [query, products]);

  if (!open) return null;

  return (
    <div className="drawer-shell fixed inset-0 z-[70]">
      <button
        type="button"
        className="drawer-shell__scrim absolute inset-0"
        aria-label={t("a11y.closeSearch")}
        onClick={onClose}
      />
      <div className="search-panel absolute inset-x-0 top-0 bg-[var(--color-white)] max-h-[min(85dvh,85vh)] overflow-y-auto">
        <div className="container search-panel__inner py-5">
          <div className="flex items-center gap-3 border-b border-black/15 pb-3">
            <span className="opacity-45">
              <IconSearch size={20} />
            </span>
            <input
              autoFocus
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder={t("search.placeholder")}
              className="flex-1 bg-transparent outline-none text-lg"
              aria-label={t("a11y.search")}
            />
            <button
              type="button"
              className="site-header__icon"
              aria-label={t("a11y.closeSearch")}
              onClick={onClose}
            >
              <IconClose size={18} />
            </button>
          </div>

          <div className="py-5">
            <h3 className="text-xs uppercase tracking-[0.15em] opacity-50 mb-4">
              {t("search.products")}
            </h3>
            {loading ? (
              <p className="text-sm opacity-50">{t("search.loading")}</p>
            ) : results.length === 0 && query.trim() ? (
              <p className="text-sm opacity-50">{t("search.noResults")}</p>
            ) : (
              <>
                <ul className="space-y-3">
                  {results.map((p, i) => (
                    <li
                      key={p.handle}
                      className="animate-fade-up"
                      style={{ animationDelay: `${i * 40}ms` }}
                    >
                      <Link
                        href={`/products/${p.handle}`}
                        onClick={onClose}
                        className="flex items-center gap-4 hover:bg-black/[0.03] p-2 -mx-2 transition-colors"
                      >
                        <StorefrontImage
                          src={p.image || ""}
                          alt={p.title}
                          width={56}
                          height={56}
                          className="w-14 h-14 object-cover bg-[var(--color-paper)] media-mono"
                        />
                        <div>
                          <div className="text-sm">{p.title}</div>
                          <div className="text-sm opacity-70">{format(p.price)}</div>
                        </div>
                      </Link>
                    </li>
                  ))}
                </ul>
                <Link
                  href="/collections/all"
                  onClick={onClose}
                  className="inline-block mt-6 text-sm link-underline"
                >
                  {t("search.viewAll")}
                </Link>
              </>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
