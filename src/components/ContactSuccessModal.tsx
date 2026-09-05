"use client";

import { useEffect } from "react";
import { useBrand } from "@/context/brand";
import { useLocale } from "@/context/locale";
import { IconClose } from "@/components/Icons";

/**
 * Branded confirmation shown after a successful ContactForm submission.
 * Mirrors the visual language of the order-confirmation screen (same logo
 * treatment, animated check mark, monochrome palette, heading font) so the
 * moment feels as polished as checkout — just as an overlay instead of a
 * full page, since the customer stays on the Contact page.
 */
export function ContactSuccessModal({
  open,
  onClose,
  name,
}: {
  open: boolean;
  onClose: () => void;
  name?: string;
}) {
  const brand = useBrand();
  const { t } = useLocale();
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") onClose();
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [open, onClose]);

  if (!open) return null;

  const firstName = name?.trim().split(/\s+/)[0];
  const thankYou = t("contact.success.thankYou");

  return (
    <div
      className="contact-success"
      role="dialog"
      aria-modal="true"
      aria-label={t("contact.success.aria")}
    >
      <button
        type="button"
        className="contact-success__veil"
        aria-label={t("a11y.close")}
        onClick={onClose}
      />

      <div className="contact-success__panel">
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
            <circle className="contact-success__check-ring" cx="32" cy="32" r="28" />
            <path
              className="contact-success__check-mark"
              d="M18.5 33.5 27.2 42 45.5 22.5"
            />
          </svg>
        </div>

        <p className="contact-success__eyebrow">{t("contact.success.eyebrow")}</p>

        <h2 className="contact-success__title heading">
          {thankYou.split("").map((ch, i) => (
            <span key={`${ch}-${i}`} style={{ animationDelay: `${420 + i * 45}ms` }}>
              {ch === " " ? "\u00A0" : ch}
            </span>
          ))}
        </h2>

        <div className="contact-success__rule" aria-hidden="true" />

        <p className="contact-success__copy">
          {firstName
            ? t("contact.success.copyNamed", { name: firstName })
            : t("contact.success.copy")}
        </p>

        <button type="button" className="btn btn-primary contact-success__cta" onClick={onClose}>
          {t("contact.success.continue")}
        </button>

        <p className="contact-success__note">
          {t("contact.success.note", { brand: brand.name })}
        </p>
      </div>
    </div>
  );
}
