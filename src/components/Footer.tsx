import { StorefrontImage } from "@/components/StorefrontImage";
import Link from "next/link";
import { getSiteContent, getStoreSettings } from "@/lib/catalog";
import { BRAND } from "@/lib/brand";
import { mapsHref, normalizeHttpUrl, telHref, whatsappHref } from "@/lib/contact";
import { FooterNavLink } from "@/components/FooterNavLink";
import { StoreVisit } from "@/components/StoreVisit";
import {
  localizeLabel,
  t,
} from "@/lib/i18n";
import { getServerLocale } from "@/lib/i18n/server";
import {
  IconBadge,
  IconBag,
  IconFacebook,
  IconInstagram,
  IconLock,
  IconMail,
  IconPhone,
  IconShipping,
  IconTikTok,
  IconWhatsApp,
} from "@/components/Icons";

const TRUST_ICONS = [IconBadge, IconShipping, IconLock, IconBag] as const;

export async function Footer() {
  const [store, content, locale] = await Promise.all([
    getStoreSettings(),
    getSiteContent(),
    getServerLocale(),
  ]);
  const brand = store.name || BRAND.name;
  const logoOnDark = store.logo_on_dark || BRAND.logoOnDark;
  const year = new Date().getFullYear();
  const phone = store.phone;
  const email = store.email;
  const wa = whatsappHref(store.whatsapp || phone);
  const tel = telHref(phone);
  const instagram = normalizeHttpUrl(store.social.instagram);
  const tiktok = normalizeHttpUrl(store.social.tiktok);
  const facebook = normalizeHttpUrl(store.social.facebook);
  const maps = mapsHref(store.maps_url, store.address);
  const hasVisit = Boolean(
    store.address ||
      store.address_ar ||
      store.address_label ||
      store.address_label_ar ||
      maps,
  );
  const tagline =
    store.description?.trim() ||
    store.seo_description?.trim() ||
    "";
  const trust = content.footer.trust;
  const customerCare = content.footer.customer_care;
  const information = content.footer.information;

  return (
    <footer className="site-footer mt-auto content-auto">
      <div className="w-full overflow-hidden dokan-accent-strip" aria-hidden />

      <div className="site-footer__trust">
        <div className="container site-footer__trust-inner">
          {trust.map((label, i) => {
            const Icon = TRUST_ICONS[i % TRUST_ICONS.length];
            return (
              <div
                key={`${label}-${i}`}
                className="site-footer__trust-item"
                style={{ animationDelay: `${i * 60}ms` }}
              >
                <span className="site-footer__trust-icon" aria-hidden>
                  <Icon size={18} />
                </span>
                <span>{localizeLabel(locale, label)}</span>
              </div>
            );
          })}
        </div>
      </div>

      <div className="site-footer__main">
        <div className="container site-footer__grid">
          <div className="site-footer__brand">
            <Link href="/" className="site-footer__logo-link" aria-label={brand}>
              <StorefrontImage
                src={logoOnDark}
                alt={brand}
                width={120}
                height={120}
                className="site-footer__logo"
                loading="lazy"
              />
            </Link>
            {tagline ? <p className="site-footer__tagline">{tagline}</p> : null}

            <div
              className="site-footer__actions"
              aria-label={t(locale, "footer.aria.contact")}
            >
              <Link
                href="/pages/contact"
                className="site-footer__action site-footer__action--primary"
              >
                <span>{t(locale, "footer.contactUs")}</span>
              </Link>
              {wa ? (
                <a
                  href={wa}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="site-footer__action site-footer__action--whatsapp"
                >
                  <IconWhatsApp size={14} aria-hidden />
                  <span>{t(locale, "footer.whatsapp")}</span>
                </a>
              ) : null}
              {email ? (
                <a href={`mailto:${email}`} className="site-footer__action">
                  <IconMail size={14} aria-hidden />
                  <span>{t(locale, "footer.email")}</span>
                </a>
              ) : null}
            </div>

            <div className="site-footer__social">
              {instagram ? (
                <a
                  href={instagram}
                  target="_blank"
                  rel="noopener noreferrer"
                  aria-label={t(locale, "a11y.instagram")}
                  className="site-footer__social-link"
                >
                  <IconInstagram size={15} />
                </a>
              ) : null}
              {tiktok ? (
                <a
                  href={tiktok}
                  target="_blank"
                  rel="noopener noreferrer"
                  aria-label={t(locale, "a11y.tiktok")}
                  className="site-footer__social-link"
                >
                  <IconTikTok size={15} />
                </a>
              ) : null}
              {facebook ? (
                <a
                  href={facebook}
                  target="_blank"
                  rel="noopener noreferrer"
                  aria-label={t(locale, "a11y.facebook")}
                  className="site-footer__social-link"
                >
                  <IconFacebook size={15} />
                </a>
              ) : null}
              {wa ? (
                <a
                  href={wa}
                  target="_blank"
                  rel="noopener noreferrer"
                  aria-label={t(locale, "a11y.whatsapp")}
                  className="site-footer__social-link"
                >
                  <IconWhatsApp size={15} />
                </a>
              ) : null}
              {email ? (
                <a
                  href={`mailto:${email}`}
                  aria-label={`Email ${email}`}
                  className="site-footer__social-link"
                >
                  <IconMail size={15} />
                </a>
              ) : null}
              {tel ? (
                <a
                  href={tel}
                  aria-label={`Call ${phone}`}
                  className="site-footer__social-link"
                >
                  <IconPhone size={15} />
                </a>
              ) : null}
            </div>
          </div>

          {hasVisit ? (
            <div className="site-footer__col site-footer__visit">
              <StoreVisit store={store} maps={maps} tone="onDark" />
            </div>
          ) : null}

          <nav
            className="site-footer__col"
            aria-label={t(locale, "footer.aria.care")}
          >
            <h3 className="site-footer__heading">
              {t(locale, "footer.customerCare")}
            </h3>
            <ul className="site-footer__links">
              {customerCare.map((item) => (
                <li key={`${item.label}-${item.href}`}>
                  <FooterNavLink href={item.href} className="site-footer__link">
                    {localizeLabel(locale, item.label, item.href)}
                  </FooterNavLink>
                </li>
              ))}
            </ul>
          </nav>

          <nav
            className="site-footer__col"
            aria-label={t(locale, "footer.aria.info")}
          >
            <h3 className="site-footer__heading">
              {t(locale, "footer.information")}
            </h3>
            <ul className="site-footer__links">
              {information.map((item) => (
                <li key={`${item.label}-${item.href}`}>
                  <FooterNavLink href={item.href} className="site-footer__link">
                    {localizeLabel(locale, item.label, item.href)}
                  </FooterNavLink>
                </li>
              ))}
            </ul>
          </nav>
        </div>
      </div>

      <div className="site-footer__legal">
        <div className="container site-footer__legal-inner">
          <p className="site-footer__copy">
            {t(locale, "footer.rights", { year, brand })}
          </p>
          <a
            href="https://rootk-eg.com"
            target="_blank"
            rel="noopener noreferrer"
            className="site-footer__credit"
            aria-label={t(locale, "a11y.developedBy")}
          >
            <span className="site-footer__credit-label">
              {t(locale, "footer.developedBy")}
            </span>
            <StorefrontImage
              src="/images/rootk-mark.png"
              alt=""
              width={48}
              height={14}
              className="site-footer__credit-logo"
              loading="lazy"
            />
            <span className="site-footer__credit-name">ROOTK Systems</span>
          </a>
        </div>
      </div>
    </footer>
  );
}
