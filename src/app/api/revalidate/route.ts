import { revalidatePath, revalidateTag } from "next/cache";
import { NextResponse } from "next/server";

/**
 * Called by the Laravel admin after catalog / settings / review changes so
 * the storefront does not wait for the ISR revalidate window.
 *
 * Auth: body.secret must match REVALIDATE_SECRET.
 * Optional body.tags invalidate tagged fetch caches (settings, etc.).
 */
export async function POST(request: Request) {
  const secret = process.env.REVALIDATE_SECRET;
  if (!secret) {
    return NextResponse.json(
      { message: "REVALIDATE_SECRET is not configured" },
      { status: 503 },
    );
  }

  let body: { secret?: string; paths?: string[]; tags?: string[] };
  try {
    body = await request.json();
  } catch {
    return NextResponse.json({ message: "Invalid JSON" }, { status: 400 });
  }

  if (body.secret !== secret) {
    return NextResponse.json({ message: "Unauthorized" }, { status: 401 });
  }

  const paths = Array.isArray(body.paths) && body.paths.length > 0 ? body.paths : ["/"];
  const tags = Array.isArray(body.tags) ? body.tags : [];
  const revalidated: string[] = [];
  const invalidatedTags: string[] = [];

  for (const tag of tags) {
    if (typeof tag !== "string" || !tag.trim()) continue;
    revalidateTag(tag.trim());
    invalidatedTags.push(tag.trim());
  }

  for (const path of paths) {
    if (typeof path !== "string" || !path.startsWith("/")) continue;
    revalidatePath(path);
    revalidated.push(path);
  }

  const catalogTouched = invalidatedTags.some((t) =>
    ["catalog", "products", "categories", "collections", "brands", "banners"].includes(
      t,
    ),
  );

  // Invalidate dynamic route templates so every collection / brand / PDP
  // refreshes after an admin catalog edit — not only the index paths.
  if (catalogTouched) {
    revalidatePath("/", "layout");
    revalidatePath("/collections", "page");
    revalidatePath("/collections/[handle]", "page");
    revalidatePath("/brands", "page");
    revalidatePath("/brands/[slug]", "page");
    revalidatePath("/products/[handle]", "page");
    revalidatePath("/search", "page");
    revalidated.push(
      "/collections/[handle]",
      "/brands/[slug]",
      "/products/[handle]",
    );
  }

  // Settings / chrome / website content must refresh the root layout and PDPs.
  if (
    invalidatedTags.some(
      (t) => t.includes("settings") || t === "site-content",
    )
  ) {
    revalidatePath("/", "layout");
    revalidatePath("/products/[handle]", "page");
    revalidatePath("/checkout", "page");
    revalidatePath("/pages/about", "page");
    revalidatePath("/pages/contact", "page");
  }

  // Legal policies edited in Admin → Policies.
  if (invalidatedTags.includes("pages") || invalidatedTags.some((t) => t.startsWith("page:"))) {
    revalidatePath("/policies/[handle]", "page");
    revalidated.push("/policies/[handle]");
    for (const tag of invalidatedTags) {
      if (!tag.startsWith("page:")) continue;
      const slug = tag.slice("page:".length).trim();
      if (!slug) continue;
      const policyPath = `/policies/${slug}`;
      revalidatePath(policyPath);
      revalidated.push(policyPath);
    }
  }

  return NextResponse.json({
    revalidated,
    tags: invalidatedTags,
    now: Date.now(),
  });
}
