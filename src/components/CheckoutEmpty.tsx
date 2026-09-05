"use client";

import Link from "next/link";
import { useBrand } from "@/context/brand";
import { useLocale } from "@/context/locale";

export function CheckoutEmpty() {
  const brand = useBrand();
  const { t } = useLocale();
  const title = t("checkout.empty");

  return (
    <section className="checkout-empty" aria-labelledby="checkout-empty-title">
      <div className="checkout-empty__veil" aria-hidden="true" />

      <div className="checkout-empty__curtain" aria-hidden="true">
        <span />
        <span />
        <span />
        <span />
        <span />
        <span />
        <span />
      </div>

      <div className="checkout-empty__panel">
        <div className="checkout-empty__logo-wrap" aria-hidden="true">
          <img
            src={brand.logo}
            alt=""
            width={140}
            height={36}
            className="checkout-empty__logo"
          />
          <span className="checkout-empty__logo-shine" />
        </div>

        <div className="checkout-empty__bag" aria-hidden="true">
          <svg viewBox="0 0 24 24" className="checkout-empty__bag-svg">
            <path
              className="checkout-empty__bag-body"
              d="M7.5 8.5h9l-.75 10.25a1.25 1.25 0 0 1-1.25 1.15H9.5a1.25 1.25 0 0 1-1.25-1.15L7.5 8.5z"
            />
            <path
              className="checkout-empty__bag-handle"
              d="M9.25 8.5V7.4a2.75 2.75 0 0 1 5.5 0V8.5"
            />
            <path
              className="checkout-empty__bag-line"
              d="M9.5 14.25h5"
            />
          </svg>
          <span className="checkout-empty__bag-glow" />
        </div>

        <p className="checkout-empty__eyebrow">{t("cart.title")}</p>

        <h1 id="checkout-empty-title" className="checkout-empty__title heading">
          {title.split("").map((ch, i) => (
            <span
              key={`${ch}-${i}`}
              style={{ animationDelay: `${480 + i * 38}ms` }}
            >
              {ch === " " ? "\u00A0" : ch}
            </span>
          ))}
        </h1>

        <div className="checkout-empty__rule" aria-hidden="true" />

        <p className="checkout-empty__copy">{t("cart.emptyHint")}</p>

        <div className="checkout-empty__stripes" aria-hidden="true">
          <span />
          <span />
          <span />
          <span />
          <span />
          <span />
          <span />
        </div>

        <Link href="/collections/all" className="btn btn-primary checkout-empty__cta">
          {t("cart.continue")}
        </Link>

        <p className="checkout-empty__note">
          {t("cart.footer", { brand: brand.name })}
        </p>
      </div>
    </section>
  );
}
