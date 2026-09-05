"use client";

import Link from "next/link";
import { StorefrontImage } from "@/components/StorefrontImage";
import { useEffect, useState, type ComponentType, type SVGProps } from "react";
import { useBrand } from "@/context/brand";
import { useCart } from "@/context/cart";
import { useWishlist } from "@/context/wishlist";
import { useLocale } from "@/context/locale";
import dynamic from "next/dynamic";
import {
  IconAccount,
  IconBag,
  IconClose,
  IconFacebook,
  IconGrid,
  IconHeart,
  IconHome,
  IconInfo,
  IconInstagram,
  IconMail,
  IconMenu,
  IconSearch,
  IconTikTok,
} from "@/components/Icons";
import { LanguageToggle } from "@/components/LanguageToggle";
import type { SiteNavItem, StoreSocial } from "@/lib/api";
import { BRAND } from "@/lib/brand";
import { normalizeHttpUrl } from "@/lib/contact";

const SearchDialog = dynamic(
  () => import("@/components/SearchDialog").then((m) => m.SearchDialog),
  { ssr: false },
);

type IconComp = ComponentType<SVGProps<SVGSVGElement> & { size?: number }>;

const NAV_ICONS: Record<string, IconComp> = {
  "/": IconHome,
  "/brands": IconGrid,
  "/collections": IconGrid,
  "/pages/about": IconInfo,
  "/pages/contact": IconMail,
  "/collections/all": IconBag,
};

export function Header({
  announcement,
  social,
  nav,
}: {
  announcement?: string;
  social?: StoreSocial;
  nav?: SiteNavItem[];
}) {
  const brand = useBrand();
  const { t, localizeLabel } = useLocale();
  const navItems = nav?.length ? nav : [...BRAND.nav];
  const { count, openCart } = useCart();
  const { count: wishCount, openWishlist } = useWishlist();
  const [menuOpen, setMenuOpen] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);
  const rawTopbar = announcement?.trim() || "";
  const topbar =
    !rawTopbar ||
    rawTopbar === BRAND.announcement ||
    /Bring nature indoors/i.test(rawTopbar)
      ? t("announcement.default")
      : rawTopbar;
  const instagram = normalizeHttpUrl(social?.instagram || "");
  const tiktok = normalizeHttpUrl(social?.tiktok || "");
  const facebook = normalizeHttpUrl(social?.facebook || "");

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 8);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  useEffect(() => {
    document.body.style.overflow = menuOpen ? "hidden" : "";
    return () => {
      document.body.style.overflow = "";
    };
  }, [menuOpen]);

  const closeMenu = () => setMenuOpen(false);

  return (
    <>
      {topbar ? <div className="site-topbar">{topbar}</div> : null}

      <header className={`site-header${scrolled ? " is-scrolled" : ""}`}>
        <div className="site-header__inner">
          <div className="site-header__left">
            <button
              type="button"
              className="site-header__icon site-header__menu-btn"
              aria-label={t("a11y.openMenu")}
              onClick={() => setMenuOpen(true)}
            >
              <IconMenu size={18} />
            </button>
            <nav className="site-header__nav">
              {navItems.map((item) => (
                <Link
                  key={`${item.label}-${item.href}`}
                  href={item.href}
                  className="link-underline"
                >
                  {localizeLabel(item.label, item.href)}
                </Link>
              ))}
            </nav>
          </div>

          <div className="site-header__center">
            <Link href="/" className="site-header__brand" aria-label={brand.name}>
              <StorefrontImage
                src={brand.logo}
                alt={brand.name}
                width={72}
                height={72}
                className="site-header__logo"
              />
              <span className="site-header__brand-spark" aria-hidden="true" />
              <span className="site-header__brand-shine" aria-hidden="true" />
            </Link>
          </div>

          <div className="site-header__right">
            <div className="site-header__actions">
              <LanguageToggle
                variant="icon"
                className="site-header__lang-icon"
              />
              <button
                type="button"
                className="site-header__icon"
                aria-label={t("a11y.search")}
                onClick={() => setSearchOpen(true)}
              >
                <IconSearch size={18} />
              </button>
              <button
                type="button"
                className="site-header__icon relative site-header__wish-btn"
                aria-label={t("a11y.wishlist")}
                onClick={openWishlist}
              >
                <IconHeart size={18} filled={wishCount > 0} />
                {wishCount > 0 ? (
                  <span className="site-header__cart-count">{wishCount}</span>
                ) : null}
              </button>
              <Link
                href="/pages/contact"
                className="site-header__icon site-header__account-btn"
                aria-label={t("a11y.account")}
              >
                <IconAccount size={18} />
              </Link>
              <button
                type="button"
                className="site-header__icon relative"
                aria-label={t("a11y.cart")}
                onClick={openCart}
              >
                <IconBag size={18} />
                {count > 0 ? (
                  <span className="site-header__cart-count">{count}</span>
                ) : null}
              </button>
            </div>
            <LanguageToggle variant="text" className="site-header__lang" />
          </div>
        </div>
      </header>

      {menuOpen && (
        <div className="mobile-menu md:hidden">
          <button
            type="button"
            className="mobile-menu__backdrop"
            aria-label={t("a11y.closeMenu")}
            onClick={closeMenu}
          />
          <aside className="mobile-menu__panel" role="dialog" aria-modal="true">
            <div className="mobile-menu__head">
              <Link href="/" onClick={closeMenu} aria-label={brand.name}>
                <StorefrontImage
                  src={brand.logo}
                  alt={brand.name}
                  width={64}
                  height={64}
                  className="site-header__logo"
                />
              </Link>
              <div className="mobile-menu__head-actions">
                <LanguageToggle variant="segmented" />
                <button
                  type="button"
                  className="site-header__icon"
                  aria-label={t("a11y.close")}
                  onClick={closeMenu}
                >
                  <IconClose size={18} />
                </button>
              </div>
            </div>

            <nav className="mobile-menu__nav">
              {navItems.map((item, i) => {
                const Icon = NAV_ICONS[item.href] ?? IconGrid;
                return (
                  <Link
                    key={`${item.label}-${item.href}`}
                    href={item.href}
                    onClick={closeMenu}
                    className="mobile-menu__link"
                    style={{ animationDelay: `${70 + i * 55}ms` }}
                  >
                    <span className="mobile-menu__icon" aria-hidden="true">
                      <Icon size={16} />
                    </span>
                    <span className="mobile-menu__label">
                      {localizeLabel(item.label, item.href)}
                    </span>
                    <span className="mobile-menu__chevron" aria-hidden="true">
                      →
                    </span>
                  </Link>
                );
              })}
            </nav>

            <div className="mobile-menu__foot">
              <p className="mobile-menu__foot-label">
                {t("nav.follow", { brand: brand.name })}
              </p>
              <div className="mobile-menu__social">
                {instagram ? (
                  <a
                    href={instagram}
                    target="_blank"
                    rel="noreferrer"
                    aria-label={t("a11y.instagram")}
                    className="mobile-menu__social-btn"
                  >
                    <IconInstagram size={16} />
                  </a>
                ) : null}
                {tiktok ? (
                  <a
                    href={tiktok}
                    target="_blank"
                    rel="noreferrer"
                    aria-label={t("a11y.tiktok")}
                    className="mobile-menu__social-btn"
                  >
                    <IconTikTok size={16} />
                  </a>
                ) : null}
                {facebook ? (
                  <a
                    href={facebook}
                    target="_blank"
                    rel="noreferrer"
                    aria-label={t("a11y.facebook")}
                    className="mobile-menu__social-btn"
                  >
                    <IconFacebook size={16} />
                  </a>
                ) : null}
              </div>
            </div>
          </aside>
        </div>
      )}

      {searchOpen && (
        <SearchDialog
          open={searchOpen}
          onClose={() => setSearchOpen(false)}
        />
      )}
    </>
  );
}
