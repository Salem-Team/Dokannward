import type { Metadata, Viewport } from "next";
import { Suspense } from "react";
import { Providers } from "@/components/Providers";
import { Footer } from "@/components/Footer";
import { JsonLd } from "@/components/JsonLd";
import { MetaPixel } from "@/components/MetaPixel";
import { BRAND } from "@/lib/brand";
import {
  getCurrency,
  getStoreSettings,
  getCheckoutSettings,
  getInventorySettings,
  getSiteContent,
} from "@/lib/catalog";
import {
  fontArabic,
  fontArabicDisplay,
  fontDisplay,
  fontEnglish,
  fontPolicy,
} from "@/lib/fonts";
import { mapsHref } from "@/lib/contact";
import {
  DEFAULT_KEYWORDS,
  organizationGraph,
  siteOrigin,
} from "@/lib/seo";
import { localeDocumentAttrs } from "@/lib/i18n";
import { getServerLocale } from "@/lib/i18n/server";
import "./globals.css";

const siteUrl = siteOrigin();

export const viewport: Viewport = {
  themeColor: [
    { media: "(prefers-color-scheme: light)", color: "#fefdf9" },
    { media: "(prefers-color-scheme: dark)", color: "#3a2a1a" },
  ],
  colorScheme: "light",
  width: "device-width",
  initialScale: 1,
  viewportFit: "cover",
};

export async function generateMetadata(): Promise<Metadata> {
  const store = await getStoreSettings();
  const name = store.name || BRAND.name;
  const description =
    store.seo_description?.trim() ||
    store.description?.trim() ||
    BRAND.description;
  const ogImage = store.seo_og_image?.trim() || BRAND.ogImage;

  return {
    metadataBase: new URL(siteUrl),
    title: {
      default: `${name} — Home Decor in Egypt`,
      template: `%s – ${name}`,
    },
    description,
    applicationName: name,
    keywords: DEFAULT_KEYWORDS,
    authors: [{ name, url: siteUrl }],
    creator: name,
    publisher: name,
    category: "shopping",
    icons: {
      icon: [
        { url: "/favicon.ico?v=5", sizes: "any" },
        { url: "/favicon-16x16.png?v=5", sizes: "16x16", type: "image/png" },
        { url: "/favicon-32x32.png?v=5", sizes: "32x32", type: "image/png" },
        { url: "/icon-192.png?v=5", sizes: "192x192", type: "image/png" },
        { url: "/icon-512.png?v=5", sizes: "512x512", type: "image/png" },
      ],
      apple: [
        { url: "/apple-touch-icon.png?v=5", sizes: "180x180", type: "image/png" },
      ],
      shortcut: ["/favicon.ico?v=5"],
    },
    manifest: "/site.webmanifest",
    openGraph: {
      type: "website",
      locale: "en_EG",
      url: siteUrl,
      siteName: name,
      title: `${name} — Home Decor in Egypt`,
      description,
      images: [
        {
          url: ogImage,
          width: 1200,
          height: 630,
          alt: `${name} — home decor in Egypt`,
          type: "image/jpeg",
        },
      ],
    },
    twitter: {
      card: "summary_large_image",
      title: `${name} — Home Decor in Egypt`,
      description,
      images: [ogImage],
    },
    robots: {
      index: true,
      follow: true,
      googleBot: {
        index: true,
        follow: true,
        "max-image-preview": "large",
        "max-snippet": -1,
        "max-video-preview": -1,
      },
    },
    appleWebApp: {
      capable: true,
      title: name,
      statusBarStyle: "default",
    },
    formatDetection: {
      telephone: true,
      email: true,
      address: false,
    },
  };
}

export default async function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  const [currency, store, checkout, inventory, content, locale] =
    await Promise.all([
      getCurrency(),
      getStoreSettings(),
      getCheckoutSettings(),
      getInventorySettings(),
      getSiteContent(),
      getServerLocale(),
    ]);
  const doc = localeDocumentAttrs(locale);

  const name = store.name || BRAND.name;
  const description =
    store.seo_description?.trim() ||
    store.description?.trim() ||
    BRAND.description;

  const graph = organizationGraph({
    name,
    description,
    email: store.email,
    phone: store.phone,
    address: store.address,
    mapsUrl: mapsHref(store.maps_url, store.address) || undefined,
    logo: store.logo,
    ogImage: store.seo_og_image,
    sameAs: [
      store.social.instagram,
      store.social.tiktok,
      store.social.facebook,
    ],
    currencyCode: currency.code,
  });

  let apiOrigin: string | null = null;
  try {
    const api = process.env.NEXT_PUBLIC_API_URL || "";
    if (api) apiOrigin = new URL(api).origin;
  } catch {
    apiOrigin = null;
  }

  return (
    <html
      lang={doc.lang}
      dir={doc.dir}
      className={`${fontEnglish.variable} ${fontDisplay.variable} ${fontArabic.variable} ${fontArabicDisplay.variable} ${fontPolicy.variable} locale-${locale}`}
    >
      <head>
        {/* Warm the API origin early so layout currency/settings don't wait on DNS+TLS. */}
        {apiOrigin ? (
          <>
            <link rel="dns-prefetch" href={apiOrigin} />
            <link rel="preconnect" href={apiOrigin} crossOrigin="anonymous" />
          </>
        ) : null}
      </head>
      <body>
        <Suspense fallback={null}>
          <MetaPixel />
        </Suspense>
        <JsonLd data={graph} />
        <Providers
          locale={locale}
          footer={<Footer />}
          currency={currency}
          announcement={store.announcement}
          social={store.social}
          nav={content.nav.items}
          checkout={checkout}
          inventory={inventory}
          brand={{
            name,
            logo:
              store.logo && !/zibra/i.test(store.logo)
                ? store.logo
                : BRAND.logo,
            logoOnDark:
              store.logo_on_dark && !/zibra/i.test(store.logo_on_dark)
                ? store.logo_on_dark
                : BRAND.logoOnDark,
          }}
        >
          {children}
        </Providers>
      </body>
    </html>
  );
}
