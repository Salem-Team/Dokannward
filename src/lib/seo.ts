import type { Metadata } from "next";
import { BRAND } from "@/lib/brand";
import type { Product, StorefrontCollection } from "@/lib/catalog";

const SITE_URL = BRAND.url.replace(/\/$/, "");
const DEFAULT_OG = BRAND.ogImage;

/** Default meta description — brand voice + Egypt commercial intent. */
const DEFAULT_DESCRIPTION = BRAND.description;

const DEFAULT_KEYWORDS = [
  BRAND.name,
  BRAND.nameAr,
  "home decor Egypt",
  "artificial plants Egypt",
  "vases Cairo",
  "bakhoor burners",
  "boho home decor",
  "candle holders Egypt",
  "wall art clocks",
].filter(Boolean);

export function siteOrigin(): string {
  return SITE_URL;
}

/** Absolute https URL for any site path or already-absolute asset. */
export function absoluteUrl(path = "/"): string {
  if (!path) return SITE_URL;
  if (/^https?:\/\//i.test(path)) return path;
  return `${SITE_URL}${path.startsWith("/") ? path : `/${path}`}`;
}

/** Prefer real product/brand art over generated avatar placeholders for social cards. */
export function socialImage(path?: string | null): string | undefined {
  if (!path) return undefined;
  if (/ui-avatars\.com/i.test(path)) return undefined;
  return path;
}

/** Avoid “Title – Dokan Ward – Dokan Ward” when SEO titles already include the brand. */
export function cleanSeoTitle(title: string, brandName: string = BRAND.name): string {
  const trimmed = title.trim();
  if (!trimmed) return brandName;
  return (
    trimmed
      .replace(
        new RegExp(`\\s*[–—|-]\\s*${escapeRegExp(brandName)}\\s*$`, "i"),
        "",
      )
      .trim() || trimmed
  );
}

function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

function clampDescription(text: string, max = 160): string {
  const clean = text.replace(/\s+/g, " ").trim();
  if (clean.length <= max) return clean;
  const cut = clean.slice(0, max - 1);
  const lastSpace = cut.lastIndexOf(" ");
  return `${(lastSpace > 80 ? cut.slice(0, lastSpace) : cut).trim()}…`;
}

function keywordList(
  keywords?: string | string[] | null,
): string[] | undefined {
  const list = Array.isArray(keywords)
    ? keywords.filter(Boolean)
    : typeof keywords === "string" && keywords.trim()
      ? keywords
          .split(/[,|]/)
          .map((k) => k.trim())
          .filter(Boolean)
      : undefined;
  return list?.length ? list : undefined;
}

/** Product title / description / keywords tuned for long-tail + Egypt intent. */
export function productSeoFields(product: Product): {
  title: string;
  description: string;
  keywords: string[];
} {
  const title = product.seoTitle?.trim() || product.title;
  const vendor = product.vendor?.trim();
  const fallback = vendor
    ? `Buy ${product.title} by ${vendor} in Egypt at Dokan Ward — premium home décor with secure checkout and nationwide delivery.`
    : `Buy ${product.title} in Egypt at Dokan Ward — premium home décor with secure checkout and nationwide delivery.`;

  const description = clampDescription(
    product.seoDescription?.trim() ||
      product.shortDescription?.trim() ||
      fallback,
  );

  const fromAdmin = keywordList(product.seoKeywords) || [];
  const auto = [
    product.title,
    vendor,
    vendor ? `${vendor} Egypt` : null,
    vendor ? `buy ${vendor} ${product.title}` : null,
    "home decor Egypt",
    "Dokan Ward",
    "دكان ورد",
  ].filter((k): k is string => Boolean(k));

  return {
    title,
    description,
    keywords: Array.from(new Set([...fromAdmin, ...auto])),
  };
}

export function brandSeoFields(
  brandName: string,
  description?: string | null,
): { title: string; description: string; keywords: string[] } {
  const title = `${brandName} Home Decor in Egypt`;
  const desc = clampDescription(
    description?.trim() ||
      `Shop ${brandName} home décor in Egypt at Dokan Ward — curated pieces, secure checkout, nationwide delivery.`,
  );
  return {
    title,
    description: desc,
    keywords: [
      brandName,
      `${brandName} Egypt`,
      `${brandName} home decor`,
      `buy ${brandName} Egypt`,
      "Dokan Ward",
      "home décor Egypt",
      "دكان ورد",
    ],
  };
}

export function collectionSeoFields(
  name: string,
  description?: string | null,
  kind: StorefrontCollection["kind"] = "collection",
): { title: string; description: string; keywords: string[] } {
  const label =
    kind === "catalog"
      ? "Home Decor Catalog"
      : kind === "category"
        ? name
        : name;
  const title =
    kind === "catalog"
      ? "Home Decor Catalog in Egypt"
      : `${label} — Home Decor in Egypt`;
  const desc = clampDescription(
    description?.trim() ||
      `Shop ${name} at Dokan Ward Egypt — home décor curated for warmth, craft, and lasting presence.`,
  );
  return {
    title,
    description: desc,
    keywords: [
      name,
      `${name} Egypt`,
      "home decor Egypt",
      "vases plants bakhoor Egypt",
      "Dokan Ward",
      "دكان ورد",
    ],
  };
}

export type PageMetaInput = {
  path: string;
  title: string;
  description?: string | null;
  image?: string | null;
  imageAlt?: string | null;
  keywords?: string | string[] | null;
  /** Defaults to true. Set false for search/checkout. */
  index?: boolean;
  ogType?: "website" | "article";
  /** Skip the root title template (use for the homepage). */
  absoluteTitle?: boolean;
  /** Live store name from admin — defaults to BRAND.name. */
  siteName?: string;
};

/**
 * Canonical + Open Graph + Twitter metadata for a single storefront URL.
 * Use on every public route so the root layout never leaks `canonical: "/"`.
 */
export function pageMetadata({
  path,
  title,
  description,
  image,
  imageAlt,
  keywords,
  index = true,
  ogType = "website",
  absoluteTitle = false,
  siteName,
}: PageMetaInput): Metadata {
  const brandName = siteName?.trim() || BRAND.name;
  const url = absoluteUrl(path);
  const desc = clampDescription(description || DEFAULT_DESCRIPTION);
  const pageTitle = cleanSeoTitle(title, brandName);
  const ogImage = absoluteUrl(image || DEFAULT_OG);
  const alt = imageAlt?.trim() || `${pageTitle} | ${brandName}`;
  const keys = keywordList(keywords);

  return {
    title:
      absoluteTitle || path === "/"
        ? { absolute: pageTitle }
        : pageTitle,
    description: desc,
    ...(keys?.length ? { keywords: keys } : {}),
    alternates: { canonical: url },
    openGraph: {
      type: ogType,
      locale: "en_EG",
      url,
      siteName: brandName,
      title: pageTitle,
      description: desc,
      images: [
        {
          url: ogImage,
          width: 1200,
          height: 630,
          alt,
          type: ogImage.endsWith(".png") ? "image/png" : "image/jpeg",
        },
      ],
    },
    twitter: {
      card: "summary_large_image",
      title: pageTitle,
      description: desc,
      images: [ogImage],
    },
    robots: index
      ? {
          index: true,
          follow: true,
          googleBot: {
            index: true,
            follow: true,
            "max-image-preview": "large",
            "max-snippet": -1,
            "max-video-preview": -1,
          },
        }
      : {
          index: false,
          follow: false,
          googleBot: { index: false, follow: false },
        },
  };
}

export type JsonLd = Record<string, unknown>;

export function organizationGraph(input: {
  name: string;
  description?: string | null;
  email?: string | null;
  phone?: string | null;
  address?: string | null;
  mapsUrl?: string | null;
  logo?: string | null;
  ogImage?: string | null;
  sameAs?: Array<string | null | undefined>;
  currencyCode?: string;
}): JsonLd {
  const orgId = `${SITE_URL}/#organization`;
  const websiteId = `${SITE_URL}/#website`;
  const storeId = `${SITE_URL}/#store`;
  const sameAs = (input.sameAs || []).filter(
    (url): url is string => Boolean(url && /^https?:\/\//i.test(url)),
  );
  const logoUrl = absoluteUrl(input.logo?.trim() || BRAND.logo);
  const ogUrl = absoluteUrl(input.ogImage?.trim() || DEFAULT_OG);

  const organization: JsonLd = {
    "@type": "Organization",
    "@id": orgId,
    name: input.name || BRAND.name,
    url: SITE_URL,
    logo: {
      "@type": "ImageObject",
      url: logoUrl,
    },
    image: ogUrl,
    description: input.description || DEFAULT_DESCRIPTION,
    email: input.email || undefined,
    telephone: input.phone || undefined,
    sameAs: sameAs.length ? sameAs : undefined,
    areaServed: {
      "@type": "Country",
      name: "Egypt",
    },
  };

  const website: JsonLd = {
    "@type": "WebSite",
    "@id": websiteId,
    url: SITE_URL,
    name: input.name || BRAND.name,
    description: input.description || DEFAULT_DESCRIPTION,
    publisher: { "@id": orgId },
    inLanguage: "en-EG",
    potentialAction: {
      "@type": "SearchAction",
      target: {
        "@type": "EntryPoint",
        urlTemplate: `${SITE_URL}/search?q={search_term_string}`,
      },
      "query-input": "required name=search_term_string",
    },
  };

  const store: JsonLd = {
    "@type": ["Store", "OnlineStore"],
    "@id": storeId,
    name: input.name || BRAND.name,
    url: SITE_URL,
    image: ogUrl,
    description: input.description || DEFAULT_DESCRIPTION,
    parentOrganization: { "@id": orgId },
    priceRange: "$$$",
    currenciesAccepted: input.currencyCode || BRAND.currency || "EGP",
    paymentAccepted: "Cash, Credit Card, Debit Card",
    email: input.email || undefined,
    telephone: input.phone || undefined,
    sameAs: sameAs.length ? sameAs : undefined,
    areaServed: {
      "@type": "Country",
      name: "Egypt",
    },
    ...(input.address
      ? {
          address: {
            "@type": "PostalAddress",
            streetAddress: input.address,
            addressCountry: "EG",
            addressLocality: "Cairo",
          },
        }
      : {
          address: {
            "@type": "PostalAddress",
            addressCountry: "EG",
            addressLocality: "Cairo",
          },
        }),
    ...(input.mapsUrl ? { hasMap: input.mapsUrl } : {}),
  };

  return {
    "@context": "https://schema.org",
    "@graph": [organization, website, store],
  };
}

export function faqPageJsonLd(
  items: Array<{ q: string; a: string }>,
): JsonLd | null {
  if (!items.length) return null;
  return {
    "@context": "https://schema.org",
    "@type": "FAQPage",
    mainEntity: items.map((item) => ({
      "@type": "Question",
      name: item.q,
      acceptedAnswer: {
        "@type": "Answer",
        text: item.a,
      },
    })),
  };
}

export function breadcrumbJsonLd(
  crumbs: Array<{ name: string; path: string }>,
): JsonLd | null {
  if (crumbs.length < 2) return null;
  return {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: crumbs.map((crumb, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: crumb.name,
      item: absoluteUrl(crumb.path),
    })),
  };
}

function priceValidUntil(): string {
  const d = new Date();
  d.setFullYear(d.getFullYear() + 1);
  return d.toISOString().slice(0, 10);
}

export type ProductJsonLdOptions = {
  shippingFee?: number;
  /** Merchant return window in days. */
  returnDays?: number;
};

function merchantOfferExtras(
  currencyCode: string,
  options: ProductJsonLdOptions = {},
): JsonLd {
  const shippingFee = Math.max(0, Number(options.shippingFee ?? 10));
  const returnDays = Math.max(1, Number(options.returnDays ?? 14));

  return {
    shippingDetails: {
      "@type": "OfferShippingDetails",
      shippingRate: {
        "@type": "MonetaryAmount",
        value: shippingFee.toFixed(2),
        currency: currencyCode,
      },
      shippingDestination: {
        "@type": "DefinedRegion",
        addressCountry: "EG",
      },
      deliveryTime: {
        "@type": "ShippingDeliveryTime",
        handlingTime: {
          "@type": "QuantitativeValue",
          minValue: 0,
          maxValue: 2,
          unitCode: "DAY",
        },
        transitTime: {
          "@type": "QuantitativeValue",
          minValue: 1,
          maxValue: 5,
          unitCode: "DAY",
        },
      },
      doesNotShip: false,
      shippingSettingsLink: absoluteUrl("/policies/shipping-policy"),
    },
    hasMerchantReturnPolicy: {
      "@type": "MerchantReturnPolicy",
      applicableCountry: "EG",
      returnPolicyCategory:
        "https://schema.org/MerchantReturnFiniteReturnWindow",
      merchantReturnDays: returnDays,
      returnMethod: "https://schema.org/ReturnByMail",
      returnFees: "https://schema.org/FreeReturn",
      returnPolicyLink: absoluteUrl("/policies/refund-policy"),
    },
    areaServed: {
      "@type": "Country",
      name: "Egypt",
    },
  };
}

export function productJsonLd(
  product: Product,
  currencyCode = "EGP",
  options: ProductJsonLdOptions = {},
): JsonLd {
  const url = absoluteUrl(`/products/${product.handle}`);
  const images = (product.images?.length ? product.images : [product.image])
    .filter((src): src is string => Boolean(src))
    .map((src) => absoluteUrl(src));

  const seo = productSeoFields(product);
  const validUntil = priceValidUntil();
  const merchant = merchantOfferExtras(currencyCode, options);

  const variantOffers =
    product.colors.length > 0
      ? product.colors.map((color) => ({
          "@type": "Offer",
          url,
          name: `${product.title} — ${color.name}`,
          sku: color.sku || product.sku || product.handle,
          priceCurrency: currencyCode,
          price: Number.parseFloat(color.price || product.price || "0").toFixed(
            2,
          ),
          priceValidUntil: validUntil,
          availability: color.inStock
            ? "https://schema.org/InStock"
            : "https://schema.org/OutOfStock",
          itemCondition: "https://schema.org/NewCondition",
          seller: {
            "@type": "Organization",
            name: BRAND.name,
            url: SITE_URL,
          },
          ...merchant,
        }))
      : null;

  const offer: JsonLd = {
    "@type": "Offer",
    url,
    priceCurrency: currencyCode,
    price: Number.parseFloat(product.price || "0").toFixed(2),
    priceValidUntil: validUntil,
    availability: product.available
      ? "https://schema.org/InStock"
      : "https://schema.org/OutOfStock",
    itemCondition: "https://schema.org/NewCondition",
    seller: {
      "@type": "Organization",
      name: BRAND.name,
      url: SITE_URL,
    },
    ...merchant,
  };

  const node: JsonLd = {
    "@context": "https://schema.org",
    "@type": "Product",
    name: product.title,
    description: seo.description,
    sku: product.sku || product.handle,
    mpn: product.sku || product.handle,
    url,
    image: images.length ? images : [absoluteUrl(DEFAULT_OG)],
    brand: product.vendor
      ? { "@type": "Brand", name: product.vendor }
      : { "@type": "Brand", name: BRAND.name },
    category: product.categorySlug || "Bags",
    offers: variantOffers?.length ? variantOffers : offer,
  };

  if (product.material) {
    node.material = product.material;
  }

  const colorNames = product.colors.map((c) => c.name).filter(Boolean);
  if (colorNames.length) {
    node.color = colorNames.join(", ");
  }

  if (product.ratingAverage && product.reviewsCount > 0) {
    node.aggregateRating = {
      "@type": "AggregateRating",
      ratingValue: Number(product.ratingAverage).toFixed(1),
      reviewCount: product.reviewsCount,
      bestRating: "5",
      worstRating: "1",
    };
  }

  if (product.reviews?.length) {
    node.review = product.reviews.slice(0, 5).map((r) => ({
      "@type": "Review",
      author: { "@type": "Person", name: r.author || "Dokan Ward client" },
      datePublished: r.createdAt
        ? String(r.createdAt).slice(0, 10)
        : undefined,
      reviewRating: {
        "@type": "Rating",
        ratingValue: String(r.rating),
        bestRating: "5",
        worstRating: "1",
      },
      name: r.title || undefined,
      reviewBody: r.body || undefined,
    }));
  }

  return node;
}

export function itemListJsonLd(
  items: Array<{ name: string; path: string; image?: string | null }>,
  listName: string,
): JsonLd | null {
  if (!items.length) return null;
  return {
    "@context": "https://schema.org",
    "@type": "ItemList",
    name: listName,
    numberOfItems: items.length,
    itemListElement: items.slice(0, 24).map((item, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: item.name,
      url: absoluteUrl(item.path),
      ...(item.image
        ? { image: absoluteUrl(item.image) }
        : {}),
    })),
  };
}

export function collectionJsonLd(
  collection: StorefrontCollection,
  path: string,
  products?: Array<{ title: string; handle: string; image?: string | null }>,
): JsonLd {
  const name = collection.display_title || collection.title;
  const seo = collectionSeoFields(
    name,
    collection.description,
    collection.kind,
  );
  const productCount = products?.length ?? collection.products_count;

  return {
    "@context": "https://schema.org",
    "@type": "CollectionPage",
    name,
    description: seo.description,
    url: absoluteUrl(path),
    image: collection.image
      ? absoluteUrl(collection.image)
      : absoluteUrl(DEFAULT_OG),
    isPartOf: { "@id": `${SITE_URL}/#website` },
    about: {
      "@type": "Thing",
      name,
    },
    ...(typeof productCount === "number"
      ? {
          mainEntity: {
            "@type": "ItemList",
            numberOfItems: productCount,
            ...(products?.length
              ? {
                  itemListElement: products.slice(0, 24).map((p, index) => ({
                    "@type": "ListItem",
                    position: index + 1,
                    url: absoluteUrl(`/products/${p.handle}`),
                    name: p.title,
                    ...(p.image ? { image: absoluteUrl(p.image) } : {}),
                  })),
                }
              : {}),
          },
        }
      : {}),
  };
}

export {
  DEFAULT_DESCRIPTION,
  DEFAULT_OG,
  DEFAULT_KEYWORDS,
};
