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
  const contact = content.contact;
  const description =
    contact.lede?.trim() ||
    store.seo_description?.trim() ||
    store.description?.trim() ||
    BRAND.description;

  return pageMetadata({
    path: "/pages/contact",
    title: contact.title?.trim()
      ? `${contact.title.trim()} — ${name}`
      : `Contact ${name}`,
    description,
    siteName: name,
    image: store.seo_og_image || BRAND.ogImage,
    keywords: [
      `Contact ${name}`,
      `${name} Egypt`,
      "luxury bag support Egypt",
      `${name} customer care`,
    ],
  });
}

export default function ContactLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}
