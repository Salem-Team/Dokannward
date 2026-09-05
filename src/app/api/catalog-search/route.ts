import { NextResponse } from "next/server";
import { getAllProducts, toSearchProduct } from "@/lib/catalog";

export const revalidate = 600;

/**
 * Slim catalog for the header search dialog. Loaded only when the user
 * opens search — keeps every other page's TTFB free of a full catalog fetch.
 */
export async function GET() {
  const products = await getAllProducts();
  return NextResponse.json(
    { data: products.map(toSearchProduct) },
    {
      headers: {
        "Cache-Control": "public, s-maxage=600, stale-while-revalidate=1200",
      },
    },
  );
}
