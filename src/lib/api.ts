import { parseLocale, DEFAULT_LOCALE, LOCALE_COOKIE, type Locale } from "@/lib/i18n";

const API_URL = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api";

export class ApiError extends Error {
  status: number;
  errors?: Record<string, string[]>;
  constructor(message: string, status: number, errors?: Record<string, string[]>) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

/** Resolve storefront locale for bilingual API payloads (server cookie or client). */
async function resolveApiLocale(): Promise<Locale> {
  if (typeof window !== "undefined") {
    try {
      const match = document.cookie.match(
        new RegExp(`(?:^|;\\s*)${LOCALE_COOKIE}=([^;]+)`),
      );
      if (match?.[1]) return parseLocale(decodeURIComponent(match[1]));
      const stored = localStorage.getItem(LOCALE_COOKIE);
      if (stored) return parseLocale(stored);
    } catch {
      /* ignore */
    }
    return DEFAULT_LOCALE;
  }

  // Static prerender must not touch cookies() or SSG/ISR aborts.
  if (process.env.NEXT_PHASE === "phase-production-build") {
    return DEFAULT_LOCALE;
  }

  try {
    const { cookies } = await import("next/headers");
    const jar = await cookies();
    return parseLocale(jar.get(LOCALE_COOKIE)?.value);
  } catch {
    return DEFAULT_LOCALE;
  }
}

async function request<T>(
  path: string,
  init?: RequestInit & { revalidate?: number | false; tags?: string[]; locale?: Locale },
): Promise<T> {
  // Default 15m — admin StorefrontRevalidator purges tags on write so
  // catalog edits still show up immediately without short polling TTLs.
  const { revalidate = 900, tags, locale: localeOpt, ...rest } = init ?? {};
  const locale = localeOpt ?? (await resolveApiLocale());
  const skipCache =
    revalidate === false ||
    (typeof rest.method === "string" && rest.method !== "GET");

  // Include locale in the URL so Next's data cache never mixes AR/EN payloads.
  const sep = path.includes("?") ? "&" : "?";
  const localizedPath = `${path}${sep}locale=${locale}`;

  const res = await fetch(`${API_URL}${localizedPath}`, {
    ...rest,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-Locale": locale,
      ...rest.headers,
    },
    ...(skipCache
      ? { cache: "no-store" as const }
      : {
          next: {
            revalidate,
            ...(tags?.length
              ? { tags: [...tags, `locale:${locale}`] }
              : { tags: [`locale:${locale}`] }),
          },
        }),
  });

  if (!res.ok) {
    const body = await res.json().catch(() => null);
    throw new ApiError(body?.message || `Request failed (${res.status})`, res.status, body?.errors);
  }

  if (res.status === 204) return undefined as T;
  return res.json();
}

export type ApiColorSize = {
  name: string;
  variant_id: string;
  sku: string;
  price: string | number;
  in_stock: boolean;
  stock?: number | null;
};

export type ApiColor = {
  variant_id: string;
  sku: string;
  name: string;
  hex: string | null;
  price: string | number;
  compare_at_price?: string | number | null;
  in_stock: boolean;
  stock?: number | null;
  image: string | null;
  images?: string[] | null;
  sizes?: ApiColorSize[];
};

export type ApiRating = {
  average: number | null;
  count: number;
};

export type ApiReview = {
  id: string;
  author: string;
  rating: number;
  title: string | null;
  body: string | null;
  recommended: boolean | null;
  created_at: string;
};

export type ApiProduct = {
  id: string;
  sku: string;
  slug: string;
  name: string;
  short_description: string | null;
  description: string | null;
  price: string | number;
  compare_at_price: string | number | null;
  material?: string | null;
  featured: boolean;
  in_stock: boolean;
  stock?: number | null;
  updated_at?: string | null;
  brand?: { id: string; name: string; slug: string | null; logo_url: string | null } | null;
  category?: { id: string; name: string; slug: string | null } | null;
  collections?: Array<{
    id: string;
    name: string;
    slug: string | null;
  }>;
  image: string | null;
  images: string[];
  colors: ApiColor[];
  /** Offered size names on detail payloads, e.g. ["37","38","39","40"]. */
  sizes?: string[];
  rating?: ApiRating;
  reviews?: ApiReview[];
  seo?: {
    title: string | null;
    description: string | null;
    keywords: string | null;
  } | null;
};

export type ProductQuery = {
  brand?: string;
  category?: string;
  collection?: string;
  q?: string;
  featured?: boolean;
  perPage?: number;
  /** When true (default), walks every Laravel page so catalogs >100 are complete. */
  allPages?: boolean;
};

function buildQuery(params: Record<string, string | number | boolean | undefined>) {
  const usp = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== "") usp.set(key, String(value));
  }
  const qs = usp.toString();
  return qs ? `?${qs}` : "";
}

/** Listing failures are treated as "no products" rather than crashing a page —
 * a brief admin outage shouldn't take the whole storefront down.
 * Walks Laravel `simplePaginate` pages so catalogs beyond `per_page` stay complete. */
export async function fetchProducts(params: ProductQuery = {}): Promise<ApiProduct[]> {
  const allPages = params.allPages !== false;
  const perPage = Math.min(100, Math.max(1, params.perPage ?? 100));
  const collected: ApiProduct[] = [];

  try {
    let pageNum = 1;
    // Hard ceiling guards against a broken `next` link looping forever.
    for (let guard = 0; guard < 50; guard += 1) {
      const qs = buildQuery({
        brand: params.brand,
        category: params.category,
        collection: params.collection,
        q: params.q,
        featured: params.featured,
        per_page: perPage,
        page: pageNum,
      });
      const page = await request<{
        data: ApiProduct[];
        links?: { next?: string | null };
      }>(`/products${qs}`, {
        tags: ["catalog", "products"],
      });

      collected.push(...(page.data || []));

      if (!allPages || !page.links?.next || (page.data || []).length < perPage) {
        break;
      }
      pageNum += 1;
    }
    return collected;
  } catch {
    return collected;
  }
}

export async function fetchProduct(handle: string): Promise<ApiProduct | null> {
  try {
    const page = await request<{ data: ApiProduct }>(`/products/${handle}`, {
      tags: ["catalog", "products", `product:${handle}`],
    });
    return page.data;
  } catch {
    return null;
  }
}

export type ApiBrand = {
  id: string;
  name: string;
  slug: string | null;
  logo_url: string | null;
  country?: string | null;
  description?: string | null;
  /** @deprecated prefer `name` — kept for older payloads */
  translated_name?: string;
};

export type ApiCategory = {
  id: string;
  name: string;
  slug: string | null;
  slug_ar?: string | null;
  description?: string | null;
  /** Homepage Shop by category banner cover (`path` in admin). */
  image?: string | null;
  /** Logo mark for the rail under the hero — omit/null keeps category off that rail. */
  logo_url?: string | null;
  parent_id?: string | null;
  collection_id?: string | null;
  position?: number;
  /** When true, category leads the Shop by category banner stack (sort + visibility there). */
  is_featured?: boolean;
  children?: ApiCategory[];
};

export type ApiCollection = {
  id: string;
  name: string;
  slug: string | null;
  slug_ar?: string | null;
  description?: string | null;
  image?: string | null;
  position?: number;
  categories?: ApiCategory[];
};

export async function fetchBrands(): Promise<ApiBrand[]> {
  try {
    const page = await request<{ data: ApiBrand[] }>("/brands?per_page=200", {
      revalidate: 60,
      tags: ["catalog", "brands"],
    });
    return page.data ?? [];
  } catch {
    return [];
  }
}

/** Root categories with nested children — admin is the source of truth.
 *  Roots are Collections (Bags, Eyewear, …) with Categories as children. */
export async function fetchCategories(): Promise<ApiCategory[]> {
  try {
    const page = await request<{ data: ApiCategory[] }>("/categories", {
      revalidate: 60,
      tags: ["catalog", "categories"],
    });
    return page.data ?? [];
  } catch {
    return [];
  }
}

/** First-class collections with nested categories. */
export async function fetchCollections(): Promise<ApiCollection[]> {
  try {
    const page = await request<{ data: ApiCollection[] }>("/collections", {
      revalidate: 600,
      tags: ["catalog", "collections", "categories"],
    });
    return page.data ?? [];
  } catch {
    return [];
  }
}

export type CheckoutItem = {
  product_id: string;
  variant_id?: string | null;
  name: string;
  price: number;
  qty: number;
  sku?: string;
};

export type CheckoutPayload = {
  recipient_name: string;
  phone: string;
  email?: string;
  line_1: string;
  line_2?: string;
  city: string;
  postal_code?: string;
  country?: string;
  latitude?: number;
  longitude?: number;
  place_name?: string;
  location_source?: "map" | "gps" | "search" | "typed";
  notes?: string;
  payment_method?: string;
  items: CheckoutItem[];
};

export type OrderConfirmation = {
  id: string;
  order_number: string;
  status: string;
  total_amount: string | number;
  payment_method?: string | null;
  payment_label?: string | null;
  payment_instructions?: string | null;
};

export function submitOrder(payload: CheckoutPayload) {
  return request<OrderConfirmation>("/checkout", {
    method: "POST",
    body: JSON.stringify(payload),
    revalidate: false,
  });
}

export type GeoPlace = {
  place_name: string | null;
  line_1: string | null;
  city: string | null;
  postal_code: string | null;
  latitude: number;
  longitude: number;
};

export async function reverseGeocode(
  lat: number,
  lng: number,
  init?: { signal?: AbortSignal },
): Promise<GeoPlace | null> {
  try {
    const body = await request<{ data: GeoPlace | null }>(
      `/geo/reverse?lat=${encodeURIComponent(String(lat))}&lng=${encodeURIComponent(String(lng))}`,
      { revalidate: false, signal: init?.signal },
    );
    return body.data ?? null;
  } catch (err) {
    if (init?.signal?.aborted) return null;
    if (err instanceof ApiError && err.status === 429) {
      throw err;
    }
    return null;
  }
}

export async function searchPlaces(
  query: string,
  init?: { signal?: AbortSignal },
): Promise<GeoPlace[]> {
  const q = query.trim();
  if (q.length < 2) return [];
  try {
    const body = await request<{ data: GeoPlace[] }>(
      `/geo/search?q=${encodeURIComponent(q)}`,
      { revalidate: false, signal: init?.signal },
    );
    return Array.isArray(body.data) ? body.data : [];
  } catch (err) {
    if (init?.signal?.aborted) return [];
    if (err instanceof ApiError && err.status === 429) {
      throw err;
    }
    return [];
  }
}

export async function fetchRoughLocation(): Promise<GeoPlace | null> {
  try {
    const body = await request<{ data: GeoPlace | null }>("/geo/ip", {
      revalidate: false,
    });
    return body.data ?? null;
  } catch {
    return null;
  }
}

export type Testimonial = {
  id: string;
  name: string;
  title?: string | null;
  company?: string | null;
  content: string;
  rating?: number | null;
  avatar_url?: string | null;
  /** Social platform key: instagram | facebook | tiktok | whatsapp | google | x | youtube */
  source?: string | null;
  is_featured?: boolean;
};

export async function fetchTestimonials(): Promise<Testimonial[]> {
  try {
    return await request<Testimonial[]>("/testimonials", {
      revalidate: 600,
      tags: ["testimonials"],
    });
  } catch {
    // Non-fatal: the storefront should keep working even if the admin
    // backend is offline or unreachable.
    return [];
  }
}

export type Currency = {
  code: string;
  symbol: string;
  position: "before" | "after";
};

export const DEFAULT_CURRENCY: Currency = { code: "EGP", symbol: "LE", position: "before" };

/** Admin-controlled from Settings → Currency. Short revalidate window so a
 * currency change in the admin reaches the storefront within a minute. */
export async function fetchCurrency(): Promise<Currency> {
  try {
    return await request<Currency>("/settings/currency", {
      revalidate: 300,
      tags: ["currency-settings", "storefront-settings"],
    });
  } catch {
    return DEFAULT_CURRENCY;
  }
}

export type StoreSocial = {
  instagram: string;
  tiktok: string;
  facebook: string;
};

export type StoreSettings = {
  name: string;
  email: string;
  phone: string;
  whatsapp: string;
  description: string;
  announcement: string;
  address_label: string;
  address_label_ar: string;
  address: string;
  address_ar: string;
  maps_url: string;
  logo: string;
  logo_on_dark: string;
  seo_description: string;
  seo_og_image: string;
  social: StoreSocial;
};


/** Reject leftover Zibra asset paths from an old admin DB. */
function sanitizeBrandAsset(value: unknown, fallback: string): string {
  const raw = typeof value === "string" ? value.trim() : "";
  if (!raw) return fallback;
  if (/zibra/i.test(raw)) return fallback;
  return raw;
}

export const DEFAULT_STORE_SETTINGS: StoreSettings = {
  name: "Dokan Ward",
  email: "hello@dokannward.com",
  phone: "+201069503631",
  whatsapp: "+201069503631",
  description:
    "Premium home décor — artificial plants, vases, bakhoor, candles, lamps, and boho pieces.",
  announcement: "Bring nature indoors — shop home decor, plants & more",
  address_label: "Serving Egypt",
  address_label_ar: "نخدم كل مصر",
  address: "Egypt",
  address_ar: "مصر",
  maps_url: "",
  logo: "/images/dokan-ward-logo.png",
  logo_on_dark: "/images/dokan-ward-logo.png",
  seo_description:
    "Dokan Ward is Egypt’s home décor destination — premium artificial plants, vases, bakhoor, candles, lamps, and boho pieces curated since 2018.",
  seo_og_image: "/images/og-share.jpg",
  social: {
    instagram: "https://www.instagram.com/dokan_ward_96/",
    tiktok: "",
    facebook: "",
  },
};

export type InventorySettings = {
  show_stock_status: boolean;
  show_stock_quantity: boolean;
};

export const DEFAULT_INVENTORY_SETTINGS: InventorySettings = {
  show_stock_status: true,
  show_stock_quantity: false,
};

export type SiteNavItem = { label: string; href: string };

export type SiteFaqItem = { q: string; a: string; tag?: string };

export type SiteContentLink = { label: string; href: string };

export type SiteContent = {
  home: {
    hero_image: string;
    hero_wordmark: string;
    hero_alt: string;
    arrivals_eyebrow: string;
    arrivals_title: string;
    arrivals_link_label: string;
    testimonials_eyebrow: string;
    testimonials_title: string;
  };
  about: {
    hero_eyebrow: string;
    hero_title: string;
    hero_subtitle: string;
    hero_image: string;
    story_eyebrow: string;
    story_title: string;
    story_image: string;
    story_paragraphs: string[];
    quote: string;
    quote_attribution: string;
    pillars_eyebrow: string;
    pillars_title: string;
    pillars: { title: string; text: string }[];
    journey_eyebrow: string;
    journey_title: string;
    journey: { title: string; text: string }[];
    edit_eyebrow: string;
    edit_title: string;
    edit_text: string;
    edit_button_label: string;
    edit_button_href: string;
    edit_image: string;
    cta_eyebrow: string;
    cta_primary_label: string;
    cta_secondary_label: string;
  };
  contact: {
    eyebrow: string;
    title: string;
    lede: string;
    form_title: string;
    form_button: string;
  };
  nav: { items: SiteNavItem[] };
  faq: {
    eyebrow: string;
    title: string;
    items: SiteFaqItem[];
  };
  footer: {
    trust: string[];
    customer_care: SiteContentLink[];
    information: SiteContentLink[];
  };
};

/** Fallbacks mirror Admin → Website defaults. */
export const DEFAULT_SITE_CONTENT: SiteContent = {
  home: {
    hero_image: "/images/hero-layers/hero-base.jpg",
    hero_wordmark: "/images/dokan-ward-logo.png",
    hero_alt: "DOKAN WARD — Home Decor",
    arrivals_eyebrow: "Just dropped",
    arrivals_title: "Fresh finds for your home",
    arrivals_link_label: "Shop all",
    testimonials_eyebrow: "Testimonials",
    testimonials_title: "What people say about Dokan Ward",
  },
  about: {
    hero_eyebrow: "About Dokan Ward",
    hero_title: "Bring nature\nindoors",
    hero_subtitle:
      "Premium home décor pieces — plants, vases, bakhoor, candles, and boho style — curated with care since 2018.",
    hero_image: "/images/hero-layers/hero-base.jpg",
    story_eyebrow: "Who we are",
    story_title: "The Story Behind Dokan Ward",
    story_image: "/images/dokan-ward-logo.png",
    story_paragraphs: [
      "Dokan Ward was founded to bring a calm, natural atmosphere into Egyptian homes — without compromising on craft or style.",
      "From artificial greenery and statement vases to bakhoor burners, candle holders, and boho accents, every piece is chosen to feel warm, elegant, and lived-in.",
      "Whether you are styling a corner, a wall, or a full room, our collections help you build spaces that feel personal and timeless.",
    ],
    quote:
      "Bring nature indoors with pieces that feel warm, elegant, and full of soul.",
    quote_attribution: "— Dokan Ward",
    pillars_eyebrow: "Our principles",
    pillars_title: "What we stand for",
    pillars: [
      {
        title: "Natural calm",
        text: "Greenery, soft materials, and organic forms that make every room feel fresher and more grounded.",
      },
      {
        title: "Thoughtful craft",
        text: "We select décor for finish, proportion, and lasting presence — pieces you will keep styling for years.",
      },
      {
        title: "Warm hospitality",
        text: "From bakhoor to trays and candlelight, our edit is made for welcoming Egyptian homes.",
      },
    ],
    journey_eyebrow: "The process",
    journey_title: "From our atelier to your home",
    journey: [
      {
        title: "Select",
        text: "We source décor that balances beauty, durability, and everyday practicality.",
      },
      {
        title: "Style",
        text: "Collections are edited into clear categories so you can shop by mood — plants, vases, boho, light, and more.",
      },
      {
        title: "Deliver",
        text: "Across Egypt, we ship with care so your new pieces arrive ready to transform the space.",
      },
    ],
    edit_eyebrow: "The shop",
    edit_title: "Home décor.\nEvery corner.",
    edit_text:
      "Explore wall art & clocks, trees, vases, bakhoor, candle holders, and boho finds — all in one curated home edit.",
    edit_button_label: "Explore collections",
    edit_button_href: "/collections",
    edit_image: "/images/hero-layers/hero-base.jpg",
    cta_eyebrow: "Visit & connect",
    cta_primary_label: "Contact us",
    cta_secondary_label: "Shop the catalog",
  },
  contact: {
    eyebrow: "Get in touch",
    title: "Contact",
    lede: "Questions about an order, a piece, or styling advice? Message us — we reply with care. Hotline: 01069503631",
    form_title: "Send a message",
    form_button: "Send message",
  },
  nav: {
    items: [
      { label: "Home", href: "/" },
      { label: "Shop", href: "/collections/all" },
      { label: "Collections", href: "/collections" },
      { label: "About", href: "/pages/about" },
      { label: "Contact", href: "/pages/contact" },
    ],
  },
  faq: {
    eyebrow: "Support",
    title: "Our Policies",
    items: [
      {
        q: "What is the return policy?",
        a: "Our goal is for every customer to be totally satisfied with their purchase. If this isn't the case, let us know and we'll do our best to work with you to make it right.",
        tag: "Returns",
      },
      {
        q: "When will I get my order?",
        a: "We will work quickly to ship your order as soon as possible. Once your order has shipped, you will receive an email with further information. Delivery times vary depending on your location.",
        tag: "Delivery",
      },
      {
        q: "Where are your products manufactured?",
        a: "Our products are manufactured globally. We carefully select our manufacturing partners to ensure our products are high quality and a fair value.",
        tag: "Origin",
      },
    ],
  },
  footer: {
    trust: [
      "Curated Home Decor",
      "Egypt-wide Delivery",
      "Secure Payments",
      "Warm Hospitality",
    ],
    customer_care: [
      { label: "Contact Us", href: "/pages/contact" },
      { label: "Shipping & Delivery", href: "/policies/shipping-policy" },
      { label: "Returns & Exchanges", href: "/policies/refund-policy" },
    ],
    information: [
      { label: "About Us", href: "/pages/about" },
      { label: "Terms & Conditions", href: "/policies/terms-of-service" },
      { label: "Privacy Policy", href: "/policies/privacy-policy" },
    ],
  },
};

function mergeSiteContent(partial: Partial<SiteContent> | null | undefined): SiteContent {
  const p = partial ?? {};
  const homeIn: Partial<SiteContent["home"]> = p.home ?? {};
  const pickHome = (key: keyof SiteContent["home"]) => {
    const value = homeIn[key];
    return typeof value === "string" && value.trim()
      ? value.trim()
      : DEFAULT_SITE_CONTENT.home[key];
  };
  return {
    home: {
      ...DEFAULT_SITE_CONTENT.home,
      ...homeIn,
      hero_image: pickHome("hero_image"),
      hero_wordmark: pickHome("hero_wordmark"),
      hero_alt: pickHome("hero_alt"),
    },
    about: {
      ...DEFAULT_SITE_CONTENT.about,
      ...(p.about ?? {}),
      story_paragraphs:
        p.about?.story_paragraphs?.length
          ? p.about.story_paragraphs
          : DEFAULT_SITE_CONTENT.about.story_paragraphs,
      pillars: p.about?.pillars?.length
        ? p.about.pillars
        : DEFAULT_SITE_CONTENT.about.pillars,
      journey: p.about?.journey?.length
        ? p.about.journey
        : DEFAULT_SITE_CONTENT.about.journey,
    },
    contact: { ...DEFAULT_SITE_CONTENT.contact, ...(p.contact ?? {}) },
    nav: {
      items:
        p.nav?.items?.length ? p.nav.items : DEFAULT_SITE_CONTENT.nav.items,
    },
    faq: {
      eyebrow:
        typeof p.faq?.eyebrow === "string" && p.faq.eyebrow.trim()
          ? p.faq.eyebrow.trim()
          : DEFAULT_SITE_CONTENT.faq.eyebrow,
      title:
        typeof p.faq?.title === "string" && p.faq.title.trim()
          ? p.faq.title.trim()
          : DEFAULT_SITE_CONTENT.faq.title,
      items: p.faq?.items?.length
        ? p.faq.items
        : DEFAULT_SITE_CONTENT.faq.items,
    },
    footer: {
      trust: p.footer?.trust?.length
        ? p.footer.trust
        : DEFAULT_SITE_CONTENT.footer.trust,
      customer_care: p.footer?.customer_care?.length
        ? p.footer.customer_care
        : DEFAULT_SITE_CONTENT.footer.customer_care,
      information: p.footer?.information?.length
        ? p.footer.information
        : DEFAULT_SITE_CONTENT.footer.information,
    },
  };
}

/** Admin → Website. Nav, about, contact, FAQ, footer, homepage titles. */
export async function fetchSiteContent(): Promise<SiteContent> {
  try {
    const data = await request<Partial<SiteContent>>("/settings/content", {
      revalidate: 600,
      tags: ["site-content", "storefront-settings"],
    });
    return mergeSiteContent(data);
  } catch {
    return DEFAULT_SITE_CONTENT;
  }
}

/** Admin → Settings → General. Drives footer / contact / about / topbar / social. */
export async function fetchStoreSettings(): Promise<StoreSettings> {
  try {
    const data = await request<Partial<StoreSettings> & {
      social?: Partial<StoreSocial>;
    }>("/settings/store", {
      revalidate: 600,
      tags: ["store-settings", "storefront-settings"],
    });
    /** Prefer live admin strings; only fill chrome-critical assets when blank. */
    const live = (value: unknown) =>
      typeof value === "string" ? value.trim() : "";
    const asset = (value: unknown, fallback: string) =>
      sanitizeBrandAsset(live(value) || fallback, fallback);

    const phone = live(data.phone);
    const whatsapp = live(data.whatsapp) || phone;

    return {
      name: asset(data.name, DEFAULT_STORE_SETTINGS.name),
      email: live(data.email),
      phone,
      whatsapp,
      description: live(data.description),
      announcement: live(data.announcement),
      address_label: live(data.address_label),
      address_label_ar: live(data.address_label_ar),
      address: live(data.address),
      address_ar: live(data.address_ar),
      maps_url: live(data.maps_url),
      logo: asset(data.logo, DEFAULT_STORE_SETTINGS.logo),
      logo_on_dark: asset(
        data.logo_on_dark,
        DEFAULT_STORE_SETTINGS.logo_on_dark,
      ),
      seo_description: live(data.seo_description),
      seo_og_image: asset(
        data.seo_og_image,
        DEFAULT_STORE_SETTINGS.seo_og_image,
      ),
      social: {
        instagram: live(data.social?.instagram),
        tiktok: live(data.social?.tiktok),
        facebook: live(data.social?.facebook),
      },
    };
  } catch {
    return DEFAULT_STORE_SETTINGS;
  }
}

export async function loadInventorySettings(
  options: { fresh?: boolean } = {},
): Promise<InventorySettings | null> {
  try {
    const data = await request<Partial<InventorySettings>>(
      "/settings/inventory",
      {
        revalidate: options.fresh ? false : 600,
        tags: ["inventory-settings", "storefront-settings"],
      },
    );
    const showStatus = Boolean(
      data.show_stock_status ?? DEFAULT_INVENTORY_SETTINGS.show_stock_status,
    );

    return {
      show_stock_status: showStatus,
      show_stock_quantity:
        showStatus &&
        Boolean(
          data.show_stock_quantity ??
            DEFAULT_INVENTORY_SETTINGS.show_stock_quantity,
        ),
    };
  } catch {
    return null;
  }
}

export async function fetchInventorySettings(
  options: { fresh?: boolean } = {},
): Promise<InventorySettings> {
  return (
    (await loadInventorySettings(options)) ?? DEFAULT_INVENTORY_SETTINGS
  );
}

export type PaymentMethod = {
  key: string;
  label: string;
  instructions: string;
};

export const DEFAULT_PAYMENT_METHOD: PaymentMethod = {
  key: "cash",
  label: "Cash on delivery",
  instructions: "Pay the courier in cash when your order arrives.",
};

export type CheckoutSettings = {
  standard_shipping_fee: number;
  shipping_company: string;
  tax_rate: number;
  tax_enabled: boolean;
  payment_methods: PaymentMethod[];
  default_payment_method: string;
  tax_enabled_message?: string;
  tax_disabled_message?: string;
};

export const DEFAULT_CHECKOUT_SETTINGS: CheckoutSettings = {
  standard_shipping_fee: 10,
  shipping_company: "Dokan Ward Delivery",
  tax_rate: 8.5,
  tax_enabled: true,
  payment_methods: [DEFAULT_PAYMENT_METHOD],
  default_payment_method: DEFAULT_PAYMENT_METHOD.key,
  tax_enabled_message: "Tax {rate}% added to this order",
  tax_disabled_message: "Prices shown without tax",
};

function checkoutAmount(value: unknown, fallback: number): number {
  const parsed = typeof value === "string" ? Number.parseFloat(value) : Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
}

function checkoutMessage(value: unknown, fallback: string): string {
  return typeof value === "string" && value.trim() ? value.trim() : fallback;
}

/**
 * A shopper must always have something to pay with, so an empty or malformed
 * list from the admin collapses to cash on delivery instead of a dead checkout.
 */
function paymentMethods(value: unknown): PaymentMethod[] {
  if (!Array.isArray(value)) return [DEFAULT_PAYMENT_METHOD];

  const seen = new Set<string>();
  const methods: PaymentMethod[] = [];

  for (const entry of value) {
    if (!entry || typeof entry !== "object") continue;
    const raw = entry as Record<string, unknown>;
    const key = typeof raw.key === "string" ? raw.key.trim() : "";
    const label = typeof raw.label === "string" ? raw.label.trim() : "";
    if (!key || !label || seen.has(key)) continue;

    seen.add(key);
    methods.push({
      key,
      label,
      instructions:
        typeof raw.instructions === "string" ? raw.instructions.trim() : "",
    });
  }

  return methods.length ? methods : [DEFAULT_PAYMENT_METHOD];
}

/**
 * Admin → Settings → Shipping / Tax. Mirrors CheckoutController math.
 *
 * Returns `null` when the admin API is unreachable so callers can keep a
 * known-good copy instead of silently repricing the order with our defaults.
 */
export async function loadCheckoutSettings(
  options: { fresh?: boolean } = {},
): Promise<CheckoutSettings | null> {
  try {
    const data = await request<Partial<CheckoutSettings>>("/settings/checkout", {
      revalidate: options.fresh ? false : 600,
      tags: ["checkout-settings", "storefront-settings"],
    });
    const methods = paymentMethods(data.payment_methods);
    const requestedDefault =
      typeof data.default_payment_method === "string"
        ? data.default_payment_method
        : "";

    return {
      payment_methods: methods,
      default_payment_method: methods.some((m) => m.key === requestedDefault)
        ? requestedDefault
        : methods[0].key,
      standard_shipping_fee: checkoutAmount(
        data.standard_shipping_fee,
        DEFAULT_CHECKOUT_SETTINGS.standard_shipping_fee,
      ),
      shipping_company:
        typeof data.shipping_company === "string"
          ? data.shipping_company.trim()
          : DEFAULT_CHECKOUT_SETTINGS.shipping_company,
      tax_rate: checkoutAmount(data.tax_rate, DEFAULT_CHECKOUT_SETTINGS.tax_rate),
      tax_enabled: Boolean(data.tax_enabled ?? DEFAULT_CHECKOUT_SETTINGS.tax_enabled),
      tax_enabled_message: checkoutMessage(
        data.tax_enabled_message,
        DEFAULT_CHECKOUT_SETTINGS.tax_enabled_message!,
      ),
      tax_disabled_message: checkoutMessage(
        data.tax_disabled_message,
        DEFAULT_CHECKOUT_SETTINGS.tax_disabled_message!,
      ),
    };
  } catch {
    return null;
  }
}

/**
 * Checkout itself requests a fresh copy because these values affect the
 * amount a shopper is about to confirm. Other surfaces may use the tagged
 * cache and are invalidated by the admin revalidator.
 */
export async function fetchCheckoutSettings(
  options: { fresh?: boolean } = {},
): Promise<CheckoutSettings> {
  return (await loadCheckoutSettings(options)) ?? DEFAULT_CHECKOUT_SETTINGS;
}

export function computeCheckoutTotals(
  subtotal: number,
  settings: Pick<
    CheckoutSettings,
    | "standard_shipping_fee"
    | "tax_rate"
    | "tax_enabled"
  >,
) {
  const shipping = Math.max(0, settings.standard_shipping_fee);
  const tax = settings.tax_enabled
    ? Math.round(subtotal * (settings.tax_rate / 100) * 100) / 100
    : 0;
  const total = Math.round((subtotal + shipping + tax) * 100) / 100;
  return { shipping, tax, total };
}

export function formatCheckoutMessage(
  template: string | undefined,
  fallback: string,
  values: Record<string, string | number>,
): string {
  const message = template?.trim() || fallback;

  return message.replace(/\{([a-z_]+)\}/gi, (token, key: string) =>
    Object.prototype.hasOwnProperty.call(values, key) ? String(values[key]) : token,
  );
}

export type CmsPage = {
  id: string;
  slug: string;
  title: string;
  content: string | null;
  meta_title: string | null;
  meta_description: string | null;
};

/** Admin → Pages. Published only. */
export async function fetchPage(
  slug: string,
  opts?: { fresh?: boolean },
): Promise<CmsPage | null> {
  try {
    return await request<CmsPage>(`/pages/${encodeURIComponent(slug)}`, {
      // Policies use fresh:true so admin edits never lag behind a stale ISR shell.
      revalidate: opts?.fresh ? false : 60,
      tags: ["pages", `page:${slug}`],
    });
  } catch {
    return null;
  }
}

export type StorefrontBanner = {
  id: string;
  title: string | null;
  subtitle: string | null;
  button_text: string | null;
  button_url: string | null;
  image_url: string | null;
  position: number;
};

/** Admin → Banners. Live (active + date window) homepage CTAs. */
export async function fetchBanners(): Promise<StorefrontBanner[]> {
  try {
    const res = await request<{ data: StorefrontBanner[] }>("/banners", {
      revalidate: 600,
      tags: ["catalog", "banners"],
    });
    return Array.isArray(res.data) ? res.data : [];
  } catch {
    return [];
  }
}


export type ReviewsPage = {
  data: ApiReview[];
  meta: { average: number | null; count: number; current_page: number; last_page: number };
};

export async function fetchProductReviews(handle: string): Promise<ReviewsPage> {
  try {
    return await request<ReviewsPage>(`/products/${handle}/reviews`, {
      revalidate: 300,
      tags: ["products", `product:${handle}`, `reviews:${handle}`],
    });
  } catch {
    return { data: [], meta: { average: null, count: 0, current_page: 1, last_page: 1 } };
  }
}

export type SubmitReviewPayload = {
  reviewer_name: string;
  reviewer_email?: string;
  rating: number;
  title?: string;
  body?: string;
  recommended?: boolean;
};

export function submitProductReview(handle: string, payload: SubmitReviewPayload) {
  return request<{ id: string; message: string }>(`/products/${handle}/reviews`, {
    method: "POST",
    body: JSON.stringify(payload),
    revalidate: false,
  });
}

export type SubmitContactPayload = {
  name: string;
  email: string;
  phone?: string;
  message: string;
};

export function submitContactMessage(payload: SubmitContactPayload) {
  return request<{ id: string; message: string }>("/contact", {
    method: "POST",
    body: JSON.stringify(payload),
    revalidate: false,
  });
}
