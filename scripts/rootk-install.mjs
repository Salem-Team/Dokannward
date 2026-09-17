#!/usr/bin/env node
/**
 * ROOTK frontend post-deploy hook.
 * Laravel install runs separately: `cd admin && php artisan rootk:install`
 */
import { existsSync, readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const manifest = join(root, ".rootk", "manifest.json");
const layout = join(root, "src", "app", "layout.tsx");

if (!existsSync(manifest)) {
  console.error("Missing .rootk/manifest.json — ROOTK product descriptor required.");
  process.exit(1);
}

const data = JSON.parse(readFileSync(manifest, "utf8"));
if (data.runtimeType !== "nextjs") {
  console.warn(`runtimeType is "${data.runtimeType}" (expected nextjs for this template).`);
}

const layoutSrc = existsSync(layout) ? readFileSync(layout, "utf8") : "";
const hasStart = layoutSrc.includes("ROOTK_TENANT_BRANDING_START");
const hasEnd = layoutSrc.includes("ROOTK_TENANT_BRANDING_END");
if (!hasStart || !hasEnd) {
  console.error("Missing ROOTK branding markers in src/app/layout.tsx");
  process.exit(1);
}

console.log("ROOTK frontend install OK.");
console.log("  manifest:", data.runtimeType, data.domain);
console.log("  branding markers: present in src/app/layout.tsx (SSR HTML)");
console.log("  Next: reads NEXT_PUBLIC_ROOTK_TENANT_* and .rootk/branding.json at runtime.");
console.log("  Admin: cd admin && php artisan rootk:install");
