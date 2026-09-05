import type { Metadata } from "next";
import { fetchCheckoutSettings } from "@/lib/api";
import CheckoutClient from "./CheckoutClient";
import { pageMetadata } from "@/lib/seo";
import { getServerLocale } from "@/lib/i18n/server";
import { t } from "@/lib/i18n";

// Shipping and tax are financial inputs. Never serve a cached checkout
// snapshot after an admin changes them in Settings.
export const dynamic = "force-dynamic";
export const revalidate = 0;

export async function generateMetadata(): Promise<Metadata> {
  const locale = await getServerLocale();
  return pageMetadata({
    path: "/checkout",
    title: t(locale, "checkout.metaTitle"),
    description: "Secure checkout for your Dokan Ward order.",
    index: false,
  });
}

export default async function CheckoutPage() {
  const settings = await fetchCheckoutSettings({ fresh: true });
  return <CheckoutClient initialSettings={settings} />;
}
