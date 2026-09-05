import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";

const TTL_MS = 60_000;

const LEGACY_COLLECTION_ALIASES: Record<string, string> = {
  "handbags-and-accessories-example-products": "bags",
  frontpage: "all",
};

type HandleCache = {
  at: number;
  collections: Set<string>;
  products: Set<string>;
  brands: Set<string>;
};

let handleCache: HandleCache | null = null;

function apiBase(): string {
  return (process.env.NEXT_PUBLIC_API_URL || "http://127.0.0.1:8000/api").replace(
    /\/$/,
    "",
  );
}

async function catalogHandles(): Promise<HandleCache> {
  if (handleCache && Date.now() - handleCache.at < TTL_MS) {
    return handleCache;
  }

  const empty: HandleCache = {
    at: Date.now(),
    collections: new Set(["all"]),
    products: new Set(),
    brands: new Set(),
  };

  try {
    const res = await fetch(`${apiBase()}/catalog/handles`, {
      headers: { Accept: "application/json" },
      next: { revalidate: 60 },
    });
    if (!res.ok) return handleCache ?? empty;

    const json = (await res.json()) as {
      collections?: string[];
      products?: string[];
      brands?: string[];
    };

    handleCache = {
      at: Date.now(),
      collections: new Set(json.collections ?? ["all"]),
      products: new Set(json.products ?? []),
      brands: new Set(json.brands ?? []),
    };
    return handleCache;
  } catch {
    return handleCache ?? empty;
  }
}

function isValidCollectionHandle(handle: string, collections: Set<string>): boolean {
  if (handle === "all") return true;
  const canonical = LEGACY_COLLECTION_ALIASES[handle] ?? handle;
  return collections.has(canonical);
}

function notFoundResponse(request: NextRequest): NextResponse {
  const url = request.nextUrl.clone();
  url.pathname = "/404";
  return NextResponse.rewrite(url, { status: 404 });
}

/**
 * - Brand slugs under /collections/* → 308 to /brands/*
 * - Unknown collection / product / brand handles → real HTTP 404 (Next.js
 *   notFound() alone can still answer 200 on ISR pages).
 */
export async function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;

  const collectionMatch = pathname.match(/^\/collections\/([^/]+)\/?$/);
  if (collectionMatch) {
    const handle = collectionMatch[1];
    if (!handle) return NextResponse.next();

    const handles = await catalogHandles();

    if (handle !== "all" && handles.brands.has(handle)) {
      const url = request.nextUrl.clone();
      url.pathname = `/brands/${handle}`;
      return NextResponse.redirect(url, 308);
    }

    if (!isValidCollectionHandle(handle, handles.collections)) {
      return notFoundResponse(request);
    }

    return NextResponse.next();
  }

  const productMatch = pathname.match(/^\/products\/([^/]+)\/?$/);
  if (productMatch) {
    const handle = productMatch[1];
    if (!handle) return NextResponse.next();

    const handles = await catalogHandles();
    if (!handles.products.has(handle)) {
      return notFoundResponse(request);
    }

    return NextResponse.next();
  }

  const brandMatch = pathname.match(/^\/brands\/([^/]+)\/?$/);
  if (brandMatch) {
    const slug = brandMatch[1];
    if (!slug) return NextResponse.next();

    const handles = await catalogHandles();
    if (!handles.brands.has(slug)) {
      return notFoundResponse(request);
    }

    return NextResponse.next();
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/collections/:handle", "/products/:handle", "/brands/:slug"],
};
