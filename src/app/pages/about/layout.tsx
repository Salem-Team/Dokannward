import type { Metadata } from "next";
import { getSiteContent, getStoreSettings } from "@/lib/catalog";
import { BRAND } from "@/lib/brand";
import { pageMetadata } from "@/lib/seo";

export async function generateMetadata(): Promise<Metadata> {
  const [store, content] = await Promise.all([
    getStoreSettings(),
    getSiteContent(),
  ]);
  const name = store.name || BRAND.name;
  const about = content.about;
  const description =
    about.hero_subtitle?.trim() ||
    store.seo_description?.trim() ||
    store.description?.trim() ||
    BRAND.description;

  return pageMetadata({
    path: "/pages/about",
    title: about.hero_title?.replace(/\n/g, " ").trim()
      ? `${about.hero_title.replace(/\n/g, " ").trim()} — ${name}`
      : `About ${name}`,
    description,
    siteName: name,
    image: store.seo_og_image || BRAND.ogImage,
    keywords: [
      `About ${name}`,
      `${name} Egypt`,
      "curated luxury Egypt",
      "premium home décor",
      "luxury fashion Cairo",
    ],
  });
}

export default function AboutLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
