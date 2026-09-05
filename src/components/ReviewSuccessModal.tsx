"use client";

import { useEffect } from "react";
import { useBrand } from "@/context/brand";
import { useLocale } from "@/context/locale";
import { IconClose } from "@/components/Icons";

/**
 * Branded confirmation after a product review is submitted.
 * Shares the Contact / Order success visual language (logo, check draw, lettering).
 */
export function ReviewSuccessModal({
  open,
  onClose,
  productTitle,
}: {
  open: boolean;
  onClose: () => void;
  productTitle?: string;
}) {
  const brand = useBrand();
  const { t } = useLocale();
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") onClose();
    };
    document.body.style.overflow = "hidden";
    window.addEventListener("keydown", onKey);
    return () => {
      document.body.style.overflow = "";
      window.removeEventListener("keydown", onKey);
    };
  }, [open, onClose]);

  if (!open) return null;

  const thankYou = t("reviews.success.thankYou");

  return (
    <div
      className="contact-success"
      role="dialog"
      aria-modal="true"
      aria-label={t("reviews.success.aria")}
    >
      <button
        type="button"
        className="contact-success__veil"
        aria-label={t("a11y.close")}
        onClick={onClose}
      />

      <div className="contact-success__panel review-success__panel">
        <button
          type="button"
          className="contact-success__close"
          aria-label={t("a11y.close")}
          onClick={onClose}
        >
          <IconClose size={16} />
        </button>

        <div className="contact-success__logo-wrap" aria-hidden="true">
          <img
            src={brand.logo}
            alt=""
            width={150}
            height={38}
            className="contact-success__logo"
          />
          <span className="contact-success__logo-shine" />
        </div>

        <div className="contact-success__check" aria-hidden="true">
          <svg viewBox="0 0 64 64" className="contact-success__check-svg">
            <circle
              className="contact-success__check-ring"
              cx="32"
              cy="32"
              r="28"
            />
            <path
              className="contact-success__check-mark"
              d="M18.5 33.5 27.2 42 45.5 22.5"
            />
          </svg>
        </div>

        <p className="contact-success__eyebrow">{t("reviews.success.eyebrow")}</p>

        <h2 className="contact-success__title heading">
          {thankYou.split("").map((ch, i) => (
            <span
              key={`${ch}-${i}`}
              style={{ animationDelay: `${420 + i * 45}ms` }}
            >
              {ch === " " ? "\u00A0" : ch}
            </span>
          ))}
        </h2>

        <div className="contact-success__rule" aria-hidden="true" />

        <p className="contact-success__copy">
          {productTitle
            ? t("reviews.success.copyNamed", { title: productTitle })
            : t("reviews.success.copy")}
        </p>

        <button
          type="button"
          className="btn btn-primary contact-success__cta"
          onClick={onClose}
        >
          {t("reviews.success.continue")}
        </button>

        <p className="contact-success__note">
          {t("product.authenticated")} · {brand.name}
        </p>
      </div>
    </div>
  );
}
