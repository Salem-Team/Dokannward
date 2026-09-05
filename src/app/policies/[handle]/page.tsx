import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { fetchPage } from "@/lib/api";
import { getStoreSettings } from "@/lib/catalog";
import { BRAND } from "@/lib/brand";
import { fontPolicy } from "@/lib/fonts";
import { pageMetadata } from "@/lib/seo";
import { getServerLocale } from "@/lib/i18n/server";
import { localizeLabel, t } from "@/lib/i18n";

/**
 * Legal policies are always read live from Admin → Policies (CMS).
 * No static HTML fallback, no ISR shell — so admin text === website text.
 */
export const dynamic = "force-dynamic";
export const revalidate = 0;

const titles: Record<string, string> = {
  "privacy-policy": "Privacy Policy",
  "terms-of-service": "Terms of Service",
  "refund-policy": "Refund & Returns Policy",
  "shipping-policy": "Shipping Policy",
};

const BUILTIN = new Set([
  "privacy-policy",
  "terms-of-service",
  "refund-policy",
  "shipping-policy",
]);

type Props = { params: Promise<{ handle: string }> };

export async function generateStaticParams() {
  // Still enumerate known handles for routing; body is always dynamic above.
  return [...BUILTIN].map((handle) => ({ handle }));
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { handle } = await params;
  const locale = await getServerLocale();
  const [page, store] = await Promise.all([
    fetchPage(handle, { fresh: true }),
    getStoreSettings(),
  ]);
  const name = store.name || BRAND.name;
  const rawTitle = page?.meta_title || page?.title || titles[handle] || "Policy";
  const title = localizeLabel(locale, rawTitle);
  const description =
    page?.meta_description?.trim() ||
    `${title} for ${name} — curated luxury shopping.`;

  return pageMetadata({
    path: `/policies/${handle}`,
    title,
    description,
    siteName: name,
    image: store.seo_og_image || BRAND.ogImage,
  });
}

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * Quill sometimes splits an email mid-edit, e.g.
 * `<a href="mailto:…">support@z</a>ibra.net` → only "support@z" is linked.
 * Merge the dangling domain back into the anchor and sync the mailto href.
 */
function repairBrokenMailtoLinks(html: string) {
  return html.replace(
    /(<a\b([^>]*\bhref\s*=\s*["']mailto:)([^"']+)(["'][^>]*)>)([^<]*)(<\/a>)([A-Za-z0-9._%+-]+(?:\.[A-Za-z]{2,})+)/gi,
    (
      full,
      _open,
      hrefPrefix,
      _oldMail,
      hrefSuffix,
      text,
      close,
      rest,
    ) => {
      const merged = `${text}${rest}`.trim();
      if (!EMAIL_RE.test(merged)) return full;
      // Incomplete link text: has @ but no proper domain TLD yet.
      if (/@[^\s@]+\.[A-Za-z]{2,}$/.test(text.trim())) return full;
      return `<a${hrefPrefix}${merged}${hrefSuffix}>${merged}${close}`;
    },
  );
}

/** Clean Quill CMS markup so storefront typography stays flush and editorial. */
function normalizePolicyHtml(html: string) {
  return repairBrokenMailtoLinks(html)
    .replace(/<span[^>]*class="[^"]*ql-ui[^"]*"[^>]*><\/span>/gi, "")
    .replace(/\s*contenteditable="[^"]*"/gi, "")
    .replace(/\s*data-list="[^"]*"/gi, "")
    .replace(/\s*class="ql-[^"]*"/gi, "")
    // Drop any Quill/paste font overrides — policy pages always use Cabin.
    .replace(/\s*style="([^"]*)"/gi, (_full, styles: string) => {
      const next = styles
        .split(";")
        .map((part) => part.trim())
        .filter((part) => part && !/^font-family\s*:/i.test(part))
        .join("; ");
      return next ? ` style="${next}"` : "";
    })
    .replace(/(?:&nbsp;|\u00a0){2,}/gi, " ")
    .replace(/<p>\s*<br\s*\/?>\s*<\/p>/gi, '<p class="policy-spacer"><br></p>')
    .replace(/(<\/?(?:ul|ol)[^>]*>)\s+/gi, "$1")
    .replace(/\s+(<\/(?:ul|ol|li)>)/gi, "$1")
    .trim();
}

export default async function PolicyPage({ params }: Props) {
  const { handle } = await params;
  const locale = await getServerLocale();
  const page = await fetchPage(handle, { fresh: true });
  const cmsHtml = page?.content?.trim() || "";

  // Single source of truth: published CMS row only. Never filesystem HTML.
  if (!cmsHtml) notFound();

  const rawTitle = page?.title || titles[handle] || "Policy";
  const title = localizeLabel(locale, rawTitle);
  const html = normalizePolicyHtml(cmsHtml);

  return (
    <article
      className={`shopify-policy ${fontPolicy.className} ${fontPolicy.variable}`}
    >
      <div className="container shopify-policy__container animate-fade-up">
        <header className="shopify-policy__title">
          <p className="shopify-policy__eyebrow">{t(locale, "policy.legal")}</p>
          <h1 className="heading">{title}</h1>
          <span className="shopify-policy__rule" aria-hidden="true" />
        </header>
        <div
          className="shopify-policy__body"
          dangerouslySetInnerHTML={{ __html: html }}
        />
      </div>
    </article>
  );
}
